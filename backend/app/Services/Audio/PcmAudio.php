<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PcmAudio
{
    public const SAMPLE_RATE = 24000;
    public const CHANNELS = 1;
    public const BYTES_PER_SAMPLE = 2;

    /**
     * @param array<int, string> $chunks
     */
    public function concatenateWithSilence(array $chunks, float $silenceSeconds = 0.45): string
    {
        $silence = str_repeat("\0", (int) round(self::SAMPLE_RATE * self::CHANNELS * self::BYTES_PER_SAMPLE * $silenceSeconds));
        $out = '';

        foreach (array_values($chunks) as $index => $chunk) {
            $out .= $chunk;
            if ($index < count($chunks) - 1) {
                $out .= $silence;
            }
        }

        return $out;
    }

    public function wavFromPcm(string $pcm): string
    {
        $dataLength = strlen($pcm);
        $byteRate = self::SAMPLE_RATE * self::CHANNELS * self::BYTES_PER_SAMPLE;
        $blockAlign = self::CHANNELS * self::BYTES_PER_SAMPLE;

        $header = 'RIFF'
            .pack('V', 36 + $dataLength)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, self::CHANNELS, self::SAMPLE_RATE, $byteRate, $blockAlign, self::BYTES_PER_SAMPLE * 8)
            .'data'
            .pack('V', $dataLength);

        return $header.$pcm;
    }

    public function durationSecondsFromPcm(string $pcm): float
    {
        return $this->durationSecondsFromByteCount(strlen($pcm));
    }

    public function durationSecondsFromByteCount(int $byteCount): float
    {
        return $byteCount / (self::SAMPLE_RATE * self::CHANNELS * self::BYTES_PER_SAMPLE);
    }

    public function temporaryPath(string $extension): string
    {
        $tmpDir = $this->temporaryDirectory();
        $base = $tmpDir.'/'.uniqid('narration-', true);

        return $base.'.'.ltrim($extension, '.');
    }

    public function appendPcmChunk($handle, string $chunk): int
    {
        return $this->writeAll($handle, $chunk);
    }

    public function appendSilence($handle, float $silenceSeconds = 0.45): int
    {
        $byteCount = (int) round(self::SAMPLE_RATE * self::CHANNELS * self::BYTES_PER_SAMPLE * $silenceSeconds);

        return $this->writeAll($handle, str_repeat("\0", $byteCount));
    }

    public function wavFileFromPcmFile(string $pcmPath, int $dataLength): string
    {
        $wavPath = $this->temporaryPath('wav');
        $complete = false;

        try {
            $input = fopen($pcmPath, 'rb');
            if (! $input) {
                throw new RuntimeException('Unable to open PCM temp file.');
            }

            $output = fopen($wavPath, 'wb');
            if (! $output) {
                fclose($input);

                throw new RuntimeException('Unable to create WAV temp file.');
            }

            try {
                $this->writeAll($output, $this->wavHeader($dataLength));
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException('Unable to copy PCM data into WAV file.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            $complete = true;

            return $wavPath;
        } finally {
            if (! $complete) {
                @unlink($wavPath);
            }
        }
    }

    public function convertPcmFileToMp3File(string $pcmPath, string $ffmpegBinary = 'ffmpeg'): string
    {
        $mp3Path = $this->temporaryPath('mp3');
        $complete = false;

        try {
            $result = Process::timeout(900)->run([
                $ffmpegBinary,
                '-y',
                '-hide_banner',
                '-loglevel',
                'error',
                '-f',
                's16le',
                '-ar',
                (string) self::SAMPLE_RATE,
                '-ac',
                (string) self::CHANNELS,
                '-i',
                $pcmPath,
                '-codec:a',
                'libmp3lame',
                '-b:a',
                '128k',
                $mp3Path,
            ]);

            if (! $result->successful() || ! is_file($mp3Path)) {
                throw new RuntimeException('ffmpeg failed: '.trim($result->errorOutput() ?: $result->output()));
            }

            $complete = true;

            return $mp3Path;
        } finally {
            if (! $complete) {
                @unlink($mp3Path);
            }
        }
    }

    public function convertWavToMp3(string $wav, string $ffmpegBinary = 'ffmpeg'): string
    {
        $wavPath = $this->temporaryPath('wav');
        $mp3Path = $this->temporaryPath('mp3');
        file_put_contents($wavPath, $wav);

        try {
            $result = Process::timeout(180)->run([
                $ffmpegBinary,
                '-y',
                '-i',
                $wavPath,
                '-codec:a',
                'libmp3lame',
                '-b:a',
                '128k',
                $mp3Path,
            ]);

            if (! $result->successful() || ! is_file($mp3Path)) {
                throw new RuntimeException('ffmpeg failed: '.trim($result->errorOutput() ?: $result->output()));
            }

            return file_get_contents($mp3Path) ?: throw new RuntimeException('Unable to read generated MP3.');
        } finally {
            @unlink($wavPath);
            @unlink($mp3Path);
        }
    }

    private function temporaryDirectory(): string
    {
        $tmpDir = storage_path('app/audio-tmp');
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create audio temp directory.');
        }

        return $tmpDir;
    }

    private function wavHeader(int $dataLength): string
    {
        $byteRate = self::SAMPLE_RATE * self::CHANNELS * self::BYTES_PER_SAMPLE;
        $blockAlign = self::CHANNELS * self::BYTES_PER_SAMPLE;

        return 'RIFF'
            .pack('V', 36 + $dataLength)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, self::CHANNELS, self::SAMPLE_RATE, $byteRate, $blockAlign, self::BYTES_PER_SAMPLE * 8)
            .'data'
            .pack('V', $dataLength);
    }

    private function writeAll($handle, string $bytes): int
    {
        if (! is_resource($handle)) {
            throw new RuntimeException('Invalid audio temp file handle.');
        }

        $writtenTotal = 0;
        $length = strlen($bytes);
        while ($writtenTotal < $length) {
            $written = fwrite($handle, substr($bytes, $writtenTotal));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write audio temp data.');
            }

            $writtenTotal += $written;
        }

        return $writtenTotal;
    }
}
