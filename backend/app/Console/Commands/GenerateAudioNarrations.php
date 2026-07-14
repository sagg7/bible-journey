<?php

namespace App\Console\Commands;

use App\Models\AudioNarration;
use App\Models\ReadingBlock;
use App\Models\StreamPlan;
use App\Models\Translation;
use App\Services\Audio\AudioNarrationTextBuilder;
use App\Services\Audio\AudioSegmentCache;
use App\Services\Audio\GeminiTtsClient;
use App\Services\Audio\PcmAudio;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAudioNarrations extends Command
{
    protected $signature = 'audio:generate-narrations
        {--plan=active : Stream plan id or active}
        {--profile=cautious_default : Active plan profile}
        {--locale=es : Plan/audio locale}
        {--translation=NVI : Bible translation code}
        {--provider=gemini : TTS provider, currently gemini}
        {--voice=Charon : Gemini prebuilt voice}
        {--model= : Gemini TTS model override}
        {--format=mp3 : Output format: mp3 or wav}
        {--block= : Generate one reading block id}
        {--limit= : Max number of blocks to process}
        {--all-nodes : Include non-main stream nodes}
        {--next-missing : Generate only the next block without a matching successful narration}
        {--max-generated= : Max successful narrations to generate in this run; defaults to 1 with --next-missing}
        {--resume : Skip successful narrations with matching hashes and files}
        {--force : Regenerate even when hashes and files match}
        {--dry-run : Build manifest only; do not call TTS or write audio}
        {--max-chars=3200 : Approximate max characters per TTS segment}
        {--sleep-ms=500 : Delay between TTS calls and generated blocks}';

    protected $description = 'Generate narrated audio for reading blocks in a stream plan.';

    public function __construct(
        private readonly AudioNarrationTextBuilder $textBuilder,
        private readonly GeminiTtsClient $gemini,
        private readonly PcmAudio $audio,
        private readonly AudioSegmentCache $segmentCache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = (string) $this->option('provider');
        if ($provider !== 'gemini') {
            $this->error('Only provider=gemini is supported right now.');

            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['mp3', 'wav'], true)) {
            $this->error('Format must be mp3 or wav.');

            return self::FAILURE;
        }
        $expectedMime = $format === 'mp3' ? 'audio/mpeg' : 'audio/wav';

        $translation = Translation::where('code', (string) $this->option('translation'))->first();
        if (! $translation) {
            $this->error('Translation not found: '.$this->option('translation'));

            return self::FAILURE;
        }

        $voice = (string) $this->option('voice');
        $model = (string) ($this->option('model') ?: config('services.gemini.tts_model', 'gemini-2.5-flash-preview-tts'));
        $blocks = $this->blocksToProcess();

        if ($blocks->isEmpty()) {
            $this->warn('No reading blocks found for the requested scope.');

            return self::SUCCESS;
        }

        if ($limit = $this->option('limit')) {
            $blocks = $blocks->take((int) $limit)->values();
        }

        $this->info(sprintf(
            'Audio narration run: blocks=%d translation=%s provider=%s voice=%s model=%s format=%s',
            $blocks->count(),
            $translation->code,
            $provider,
            $voice,
            $model,
            $format
        ));

        $ok = 0;
        $skipped = 0;
        $failed = 0;
        $maxGenerated = $this->maxGenerated();
        $nextMissingCandidateFound = false;

        foreach ($blocks as $index => $block) {
            $number = $index + 1;
            $label = "#{$block->id} ".($block->display_label_es ?: $block->display_reference ?: $block->source_map);
            $this->line("[{$number}/{$blocks->count()}] {$label}");

            try {
                $source = $this->textBuilder->build($block, $translation, $voice, $model, (int) $this->option('max-chars'));
                if (empty($source['verses'])) {
                    $this->warn('  no text for '.$translation->code);
                    $failed++;
                    $this->markFailed($block, $translation, $provider, $voice, $model, $source, 'No verses found for translation.');

                    if ($this->option('next-missing')) {
                        break;
                    }

                    continue;
                }

                $existing = $this->findExistingNarration($block, $translation, $provider, $voice, $model);
                $existingFile = $existing?->path
                    ? Storage::disk($existing->disk ?: 'public')->exists($existing->path)
                    : false;
                $matches = $existing?->status === 'success'
                    && $existing->source_hash === $source['source_hash']
                    && $existing->prompt_hash === $source['prompt_hash']
                    && $existing->mime_type === $expectedMime
                    && $existingFile;

                if ($this->option('next-missing') && $matches && ! $this->option('force')) {
                    $skipped++;

                    continue;
                }

                if ($this->option('next-missing')) {
                    $nextMissingCandidateFound = true;
                }

                if ($this->option('dry-run')) {
                    $this->line(sprintf(
                        '  dry-run: verses=%d segments=%d chars=%d existing=%s',
                        count($source['verses']),
                        count($source['segments']),
                        collect($source['segments'])->sum(fn ($segment) => mb_strlen($segment)),
                        $matches ? 'yes' : 'no'
                    ));
                    $skipped++;

                    if ($this->option('next-missing')) {
                        break;
                    }

                    continue;
                }

                $narration = $this->findOrCreateNarration($block, $translation, $provider, $voice, $model, $source);

                if ($matches && $this->option('resume') && ! $this->option('force')) {
                    $this->line('  skip: matching audio already exists');
                    $skipped++;

                    continue;
                }

                $this->generate($narration, $source, $format);
                $ok++;

                if ($maxGenerated !== null && $ok >= $maxGenerated) {
                    break;
                }

                if (($sleepMs = (int) $this->option('sleep-ms')) > 0) {
                    usleep($sleepMs * 1000);
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error('  failed: '.$e->getMessage());

                if ($this->option('next-missing')) {
                    break;
                }
            }
        }

        if ($this->option('next-missing') && ! $nextMissingCandidateFound && $ok === 0 && $failed === 0) {
            $this->info('No missing narrations found.');
        }

        $this->info("Done. generated={$ok} skipped={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function maxGenerated(): ?int
    {
        $raw = $this->option('max-generated');

        if ($raw !== null && $raw !== '') {
            return max(1, (int) $raw);
        }

        return $this->option('next-missing') ? 1 : null;
    }

    /**
     * @return Collection<int, ReadingBlock>
     */
    private function blocksToProcess(): Collection
    {
        if ($blockId = $this->option('block')) {
            return ReadingBlock::with(['crs', 'startBook', 'endBook'])
                ->where('id', (int) $blockId)
                ->get();
        }

        $planOption = (string) $this->option('plan');
        $plan = $planOption === 'active'
            ? StreamPlan::latestPublished((string) $this->option('profile'), (string) $this->option('locale'), true)
            : StreamPlan::findOrFail((int) $planOption);

        if (! $plan) {
            return collect();
        }

        $nodesQuery = $plan->nodes()
            ->with([
                'crs.blocks' => fn ($q) => $q->with(['crs', 'startBook', 'endBook'])->orderBy('display_order'),
            ])
            ->reorder()
            ->orderBy('rank');

        if (! $this->option('all-nodes')) {
            $nodesQuery->where('is_main_stream_node', true);
        }

        $blocks = collect();
        foreach ($nodesQuery->get() as $node) {
            foreach ($node->crs?->blocks ?? [] as $block) {
                if (! $block->required_in_complete_mode || ! $block->start_book_id) {
                    continue;
                }

                if (! $blocks->has($block->id)) {
                    $blocks->put($block->id, $block);
                }
            }
        }

        return $blocks->values();
    }

    /**
     * @param array{source_hash:string,prompt_hash:string} $source
     */
    private function findExistingNarration(
        ReadingBlock $block,
        Translation $translation,
        string $provider,
        string $voice,
        string $model,
    ): ?AudioNarration {
        return AudioNarration::where('reading_block_id', $block->id)
            ->where('translation_id', $translation->id)
            ->where('provider', $provider)
            ->where('voice', $voice)
            ->where('model', $model)
            ->where('prompt_version', AudioNarrationTextBuilder::PROMPT_VERSION)
            ->first();
    }

    /**
     * @param array{source_hash:string,prompt_hash:string} $source
     */
    private function findOrCreateNarration(
        ReadingBlock $block,
        Translation $translation,
        string $provider,
        string $voice,
        string $model,
        array $source,
    ): AudioNarration {
        return AudioNarration::updateOrCreate(
            [
                'reading_block_id' => $block->id,
                'translation_id' => $translation->id,
                'provider' => $provider,
                'voice' => $voice,
                'model' => $model,
                'prompt_version' => AudioNarrationTextBuilder::PROMPT_VERSION,
            ],
            [
                'locale' => (string) $this->option('locale'),
                'source_hash' => $source['source_hash'],
                'prompt_hash' => $source['prompt_hash'],
            ]
        );
    }

    /**
     * @param array{
     *   segments:array<int,string>,
     *   source_hash:string,
     *   prompt_hash:string,
     *   prompt:string,
     * } $source
     */
    private function generate(AudioNarration $narration, array $source, string $format): void
    {
        $narration->forceFill([
            'status' => 'generating',
            'attempt_count' => $narration->attempt_count + 1,
            'error_message' => null,
        ])->save();

        try {
            $previousDisk = $narration->disk ?: 'public';
            $previousPath = $narration->path;
            $segmentCount = count($source['segments']);
            $pcmPath = $this->audio->temporaryPath('pcm');
            $outputPath = null;
            $cacheHits = 0;
            $cacheWrites = 0;

            if ($this->option('force')) {
                $this->segmentCache->deleteRun($narration, $source);
            }

            $pcmHandle = fopen($pcmPath, 'wb');
            if (! $pcmHandle) {
                throw new \RuntimeException('Unable to create PCM temp file.');
            }

            try {
                try {
                    $pcmBytes = 0;
                    foreach ($source['segments'] as $segmentIndex => $segment) {
                        if ($segmentIndex > 0) {
                            $pcmBytes += $this->audio->appendSilence($pcmHandle);
                        }

                        $segmentPath = $this->segmentCache->segmentPath($narration, $source, $segmentIndex, $segment);
                        if ($this->segmentCache->has($segmentPath)) {
                            $cacheHits++;
                            $this->line(sprintf(
                                '  cached segment %d/%d bytes=%d',
                                $segmentIndex + 1,
                                $segmentCount,
                                $this->segmentCache->byteSize($segmentPath)
                            ));
                        } else {
                            $this->line('  tts segment '.($segmentIndex + 1).'/'.$segmentCount.' chars='.mb_strlen($segment));
                            $chunk = $this->gemini->synthesizePcm(
                                $segment,
                                $narration->voice,
                                $narration->model,
                                $source['prompt']
                            );
                            $this->segmentCache->put($segmentPath, $chunk);
                            $cacheWrites++;
                            unset($chunk);

                            if ($segmentIndex < $segmentCount - 1 && ($sleepMs = (int) $this->option('sleep-ms')) > 0) {
                                usleep($sleepMs * 1000);
                            }
                        }

                        $pcmBytes += $this->segmentCache->appendTo($pcmHandle, $segmentPath);
                    }
                } finally {
                    fclose($pcmHandle);
                }

                $duration = $this->audio->durationSecondsFromByteCount($pcmBytes);
                $outputPath = $format === 'mp3'
                    ? $this->audio->convertPcmFileToMp3File($pcmPath, (string) config('services.audio_narration.ffmpeg_binary', 'ffmpeg'))
                    : $this->audio->wavFileFromPcmFile($pcmPath, $pcmBytes);
                $extension = $format === 'mp3' ? 'mp3' : 'wav';
                $mime = $format === 'mp3' ? 'audio/mpeg' : 'audio/wav';
                $path = $this->path($narration, $source['source_hash'], $extension);
                $stream = fopen($outputPath, 'rb');
                if (! $stream) {
                    throw new \RuntimeException('Unable to read generated audio file.');
                }

                try {
                    Storage::disk('public')->put($path, $stream);
                } finally {
                    fclose($stream);
                }

                $byteSize = filesize($outputPath) ?: 0;
                if ($previousPath && $previousPath !== $path) {
                    Storage::disk($previousDisk)->delete($previousPath);
                }

                $narration->forceFill([
                    'status' => 'success',
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $mime,
                    'byte_size' => $byteSize,
                    'duration_seconds' => round($duration, 3),
                    'segment_count' => count($source['segments']),
                    'source_hash' => $source['source_hash'],
                    'prompt_hash' => $source['prompt_hash'],
                    'generated_at' => now(),
                    'error_message' => null,
                ])->save();

                $this->line(sprintf('  saved: %s %.1fs %s bytes', $path, $duration, $byteSize));
                $this->line(sprintf('  segment cache: reused=%d generated=%d', $cacheHits, $cacheWrites));
                $this->segmentCache->deleteRun($narration, $source);
            } finally {
                @unlink($pcmPath);
                if ($outputPath) {
                    @unlink($outputPath);
                }
            }
        } catch (Throwable $e) {
            $narration->forceFill([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
            ])->save();

            throw $e;
        }
    }

    /**
     * @param array{source_hash:string,prompt_hash:string} $source
     */
    private function markFailed(
        ReadingBlock $block,
        Translation $translation,
        string $provider,
        string $voice,
        string $model,
        array $source,
        string $message,
    ): void {
        if ($this->option('dry-run')) {
            return;
        }

        $narration = $this->findOrCreateNarration($block, $translation, $provider, $voice, $model, $source);
        $narration->forceFill([
            'status' => 'failed',
            'error_message' => $message,
        ])->save();
    }

    private function path(AudioNarration $narration, string $sourceHash, string $extension): string
    {
        $translation = $narration->translation?->code ?? 'unknown';
        $voice = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($narration->voice)) ?: 'voice';

        return sprintf(
            'audio/narrations/%s/gemini-%s/%06d-%s.%s',
            $translation,
            $voice,
            $narration->reading_block_id,
            substr($sourceHash, 0, 12),
            $extension
        );
    }
}
