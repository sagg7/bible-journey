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
        return strlen($pcm) / (self::SAMPLE_RATE * self::CHANNELS * self::BYTES_PER_SAMPLE);
    }

    public function convertWavToMp3(string $wav, string $ffmpegBinary = 'ffmpeg'): string
    {
        $tmpDir = storage_path('app/audio-tmp');
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create audio temp directory.');
        }

        $base = $tmpDir.'/'.uniqid('narration-', true);
        $wavPath = $base.'.wav';
        $mp3Path = $base.'.mp3';
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
}
