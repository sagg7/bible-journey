<?php

namespace App\Console\Commands;

use App\Models\Passage;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Descarga el texto de dominio público de los pasajes ya existentes en la BD
 * desde el proveedor configurado (wldeh/bible-api) y lo guarda en `passage_texts`.
 *
 * Solo afecta a traducciones de dominio público mapeadas en config/bible.php.
 * Las protegidas (NVI/NIV/RVR60) nunca se descargan: la app las muestra solo-referencia.
 *
 * Uso:  php artisan bible:fetch            (todas las versiones PD)
 *       php artisan bible:fetch WEB        (una sola)
 */
class FetchPassageTexts extends Command
{
    protected $signature = 'bible:fetch {version? : Código de traducción (ej. WEB, RVA1909)} {--force : Re-descargar aunque ya exista}';

    protected $description = 'Descarga texto bíblico de dominio público para los pasajes existentes';

    public function handle(): int
    {
        $base = rtrim(config('bible.provider.base'), '/');
        $versions = config('bible.versions');
        $bookSlugs = config('bible.book_slugs');

        $only = $this->argument('version');
        if ($only) {
            $versions = array_intersect_key($versions, [$only => true]);
            if (empty($versions)) {
                $this->error("La versión {$only} no es de dominio público o no está mapeada en config/bible.php.");

                return self::FAILURE;
            }
        }

        $passages = Passage::with('book')->get();

        foreach ($versions as $code => $providerVersion) {
            $translation = Translation::where('code', $code)->first();
            if (! $translation) {
                $this->warn("No existe la traducción {$code} en la BD; sáltala.");

                continue;
            }

            $this->info("== {$code} ({$providerVersion}) ==");

            foreach ($passages as $passage) {
                $bookSlug = $bookSlugs[$providerVersion][$passage->book->slug] ?? null;
                if (! $bookSlug) {
                    $this->warn("  Sin mapeo de libro para {$passage->book->slug} en {$providerVersion}; sáltalo.");

                    continue;
                }

                $exists = $passage->texts()->where('translation_id', $translation->id)->exists();
                if ($exists && ! $this->option('force')) {
                    continue;
                }

                $verses = $this->fetchPassage($base, $providerVersion, $bookSlug, $passage);
                if (empty($verses)) {
                    $this->warn("  {$passage->reference_label}: sin texto obtenido.");

                    continue;
                }

                $content = collect($verses)->map(fn ($v) => $v['v'].'. '.$v['t'])->implode("\n");

                $passage->texts()->updateOrCreate(
                    ['translation_id' => $translation->id],
                    ['content' => $content, 'verses' => $verses]
                );

                $this->line("  ✓ {$passage->reference_label} (".count($verses).' v.)');
            }
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{v:int, t:string}>
     */
    private function fetchPassage(string $base, string $version, string $bookSlug, Passage $passage): array
    {
        $chapterStart = $passage->chapter_start;
        $chapterEnd = $passage->chapter_end ?: $chapterStart;
        $out = [];

        for ($chapter = $chapterStart; $chapter <= $chapterEnd; $chapter++) {
            $from = ($chapter === $chapterStart) ? ($passage->verse_start ?: 1) : 1;
            $to = ($chapter === $chapterEnd && $passage->verse_end) ? $passage->verse_end : 200; // 200 = hasta el final del capítulo

            for ($verse = $from; $verse <= $to; $verse++) {
                $url = "{$base}/{$version}/books/{$bookSlug}/chapters/{$chapter}/verses/{$verse}.json";

                $resp = null;
                for ($attempt = 0; $attempt < 4; $attempt++) {
                    usleep(120_000); // 120ms: cortesía con el CDN, evita throttling
                    try {
                        $resp = Http::timeout(20)->get($url);
                    } catch (\Throwable $e) {
                        $resp = null; // timeout/conexión: reintentar con backoff
                        usleep(500_000 * ($attempt + 1));

                        continue;
                    }
                    if ($resp->successful() || $resp->status() === 404) {
                        break; // 404 = fin de capítulo real; éxito = ok. Otros (429/5xx) → reintentar.
                    }
                    usleep(500_000 * ($attempt + 1)); // backoff ante throttling
                }

                if ($resp === null || $resp->status() === 404) {
                    break; // fin del capítulo
                }
                if (! $resp->successful() || ! is_array($resp->json()) || ! isset($resp->json()['text'])) {
                    // Error persistente (no 404): no truncar en silencio.
                    $this->warn("    aviso: fallo al traer {$bookSlug} {$chapter}:{$verse} (status {$resp->status()})");

                    break;
                }

                $text = $this->clean((string) $resp->json()['text']);
                if ($text !== '') {
                    $out[] = ['v' => $verse, 't' => $text];
                }
            }
        }

        return $out;
    }

    /**
     * Limpia marcadores de notas al pie / referencias cruzadas incrustadas.
     */
    private function clean(string $text): string
    {
        // Quita referencias cruzadas incrustadas de es-rv09: "16.1 cp. 15.55.", "17.45 ver. 6.", etc.
        // Patrón: <cap>.<verso> (cp|ver|comp|cf). <referencias numéricas>
        $text = preg_replace('/\s*\d+\.\d+\s+(?:cp|ver|comp|cf)\.\s*[\d.,;:]+/u', ' ', $text);
        // Pasada extra por marcadores residuales sin números finales.
        $text = preg_replace('/\s*\d+\.\d+\s+(?:cp|ver|comp|cf)\.\s*/u', ' ', $text);
        // Quita marcadores tipo [1] o {N}.
        $text = preg_replace('/[\[\{]\d+[\]\}]/u', '', $text);
        // Normaliza espacios y espacios antes de puntuación.
        $text = preg_replace('/\s+([,.;:])/u', '$1', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
