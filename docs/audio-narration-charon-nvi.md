# Audio Narration: NVI + Gemini Charon

Pipeline for generating narrated audio per chronological reading block.

## Scope

- Translation: `NVI`
- Provider: `gemini`
- Voice: `Charon`
- Default model: `gemini-2.5-flash-preview-tts`
- Output: one audio file per `reading_block`
- Storage: `storage/app/public/audio/narrations/{translation}/gemini-{voice}/...`
- Manifest: `audio_narrations`

NVI is currently `is_test_only=1`, so API responses expose NVI audio only to users with test access.

## Environment

Production needs:

```env
GEMINI_API_KEY=...
GEMINI_TTS_MODEL=gemini-2.5-flash-preview-tts
GEMINI_TTS_TIMEOUT=240
AUDIO_NARRATION_FFMPEG_BINARY=ffmpeg
```

If `ffmpeg` is unavailable on the host, run with `--format=wav`.

Production currently uses a user-space static ffmpeg binary:

```env
AUDIO_NARRATION_FFMPEG_BINARY=/home/codeshor/bin/ffmpeg-static/ffmpeg
```

This keeps generated files as MP3 instead of large WAV files on shared hosting.

## Commands

Run migrations:

```bash
php artisan migrate --force
```

Audit the first NVI block without generating audio:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --limit=1 \
  --dry-run
```

Generate a small smoke batch:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --limit=5 \
  --resume
```

Generate only the next missing reading, intended for the daily free-tier job:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --next-missing \
  --resume
```

Generate a small daily paid-tier batch. At the 2.5 Flash Preview TTS Standard
price ($0.50/M input text tokens + $10/M output audio tokens), three readings
keeps the expected daily spend around $0.50 when average output is roughly
9-11 minutes per reading:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --next-missing \
  --max-generated=3 \
  --resume
```

The Laravel scheduler runs that command once per day at `03:20` app/server time and appends output to:

```text
storage/logs/audio-narrations-schedule.log
```

Resume the full main chronological stream:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --resume
```

Include non-main stream nodes if needed:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --all-nodes \
  --resume
```

Regenerate a single block:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --block=224 \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --force
```

Tune segment size if Gemini has trouble with long blocks:

```bash
php artisan audio:generate-narrations \
  --translation=NVI \
  --voice=Charon \
  --format=mp3 \
  --max-chars=1000 \
  --sleep-ms=2500 \
  --resume
```

Observed production note: `gemini-3.1-flash-tts-preview` returned an empty audio response on a long Genesis block (`finish=OTHER`). `gemini-2.5-flash-preview-tts` completed the same block successfully, so keep 2.5 as the default until 3.1 is retested.

## Validation

Useful SQL:

```sql
SELECT status, COUNT(*)
FROM audio_narrations
WHERE provider = 'gemini'
  AND voice = 'Charon'
GROUP BY status;

SELECT reading_block_id, status, duration_seconds, byte_size, error_message
FROM audio_narrations
WHERE provider = 'gemini'
  AND voice = 'Charon'
ORDER BY updated_at DESC
LIMIT 20;
```

Expected active main-stream local size as of this implementation: `589` reading blocks.
