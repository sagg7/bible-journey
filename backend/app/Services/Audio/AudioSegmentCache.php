<?php

namespace App\Services\Audio;

use App\Models\AudioNarration;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class AudioSegmentCache
{
    /**
     * @param array{source_hash:string,prompt_hash:string} $source
     */
    public function segmentPath(AudioNarration $narration, array $source, int $segmentIndex, string $segment): string
    {
        $segmentHash = substr(hash('sha256', $segment), 0, 16);

        return $this->runDirectory($narration, $source).'/'.sprintf('%04d-%s.pcm', $segmentIndex + 1, $segmentHash);
    }

    public function has(string $path): bool
    {
        return is_file($path) && (filesize($path) ?: 0) > 0;
    }

    public function byteSize(string $path): int
    {
        return (int) (filesize($path) ?: 0);
    }

    public function put(string $path, string $bytes): int
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create audio segment cache directory.');
        }

        $tmpPath = $directory.'/'.basename($path).'.'.uniqid('tmp-', true);
        $expectedBytes = strlen($bytes);
        $written = file_put_contents($tmpPath, $bytes, LOCK_EX);
        if ($written !== $expectedBytes) {
            @unlink($tmpPath);

            throw new RuntimeException('Unable to write audio segment cache file.');
        }

        if (is_file($path) && ! @unlink($path)) {
            @unlink($tmpPath);

            throw new RuntimeException('Unable to replace audio segment cache file.');
        }

        if (! rename($tmpPath, $path)) {
            @unlink($tmpPath);

            throw new RuntimeException('Unable to finalize audio segment cache file.');
        }

        return $written;
    }

    public function appendTo($handle, string $path): int
    {
        if (! is_resource($handle)) {
            throw new RuntimeException('Invalid audio output handle.');
        }

        $input = fopen($path, 'rb');
        if (! $input) {
            throw new RuntimeException('Unable to open cached audio segment.');
        }

        try {
            $copied = stream_copy_to_stream($input, $handle);
            if ($copied === false) {
                throw new RuntimeException('Unable to append cached audio segment.');
            }

            return (int) $copied;
        } finally {
            fclose($input);
        }
    }

    /**
     * @param array{source_hash:string,prompt_hash:string} $source
     */
    public function deleteRun(AudioNarration $narration, array $source): void
    {
        $directory = $this->runDirectory($narration, $source);
        $base = realpath($this->baseDirectory());
        $target = realpath($directory);

        if (! $base || ! $target || ! str_starts_with($target, $base.DIRECTORY_SEPARATOR)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($target);
    }

    /**
     * @param array{source_hash:string,prompt_hash:string} $source
     */
    private function runDirectory(AudioNarration $narration, array $source): string
    {
        $identity = hash('sha256', implode('|', [
            'v1',
            (string) $narration->reading_block_id,
            (string) $narration->translation_id,
            $narration->provider,
            $narration->voice,
            $narration->model,
            $narration->prompt_version,
            $source['source_hash'],
            $source['prompt_hash'],
        ]));

        return $this->baseDirectory()
            .'/'.sprintf('%06d', $narration->reading_block_id)
            .'/'.substr($identity, 0, 2)
            .'/'.$identity;
    }

    private function baseDirectory(): string
    {
        return storage_path('app/audio-segment-cache');
    }
}
