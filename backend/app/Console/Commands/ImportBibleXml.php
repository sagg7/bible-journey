<?php

namespace App\Console\Commands;

use App\Models\Translation;
use App\Services\Bible\BibleXmlImportException;
use App\Services\Bible\BibleXmlImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports a Bible translation from the simple
 * <bible><testament><book number="N"><chapter number="N"><verse number="N">
 * XML format used by github.com/Beblia/Holy-Bible-XML-Format.
 *
 * Only use this for translations that are actually licensed for this app —
 * see Translation::can_display_full_text / license_status. As of 2026-07-02
 * that's public-domain KJV only; NVI/RVR1960/RVR1995/TLA/NIV are pending a
 * real license (API.Bible / YouVersion Platform), see HANDOFF.md.
 *
 * A web equivalent (upload/URL) lives at App\Filament\Pages\ImportBibleXml —
 * both entry points share App\Services\Bible\BibleXmlImportService so the
 * license safety gate can't be bypassed by either one.
 */
class ImportBibleXml extends Command
{
    protected $signature = 'bible:import-xml
                            {code : Translation code, e.g. KJV}
                            {--url= : Raw XML URL}
                            {--source= : Local XML file path (alternative to --url)}
                            {--dry-run : Parse and report without saving}';

    protected $description = 'Import a translation from the Beblia Holy-Bible-XML-Format schema';

    public function handle(BibleXmlImportService $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $code = strtoupper($this->argument('code'));

        $translation = Translation::where('code', $code)->first();
        if (! $translation) {
            $this->error("Traducción {$code} no existe en la tabla translations. Créala primero (metadata + licencia).");

            return self::FAILURE;
        }

        $xmlPath = $this->resolveSource();
        if (! $xmlPath) {
            return self::FAILURE;
        }

        $this->info("Fuente : {$xmlPath}");
        $this->info('SHA-256: '.hash_file('sha256', $xmlPath));

        $bar = $this->output->createProgressBar(66);
        $bar->start();

        try {
            $stats = $importer->import(
                $translation,
                $xmlPath,
                $this->option('url'),
                $dryRun,
                onBook: fn () => $bar->advance(),
            );
        } catch (BibleXmlImportException $e) {
            $bar->finish();
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine();
        $this->info("Listo. Libros={$stats['books']} Capítulos={$stats['chapters']} Versículos={$stats['verses']}");

        return self::SUCCESS;
    }

    private function resolveSource(): ?string
    {
        if ($source = $this->option('source')) {
            return file_exists($source) ? realpath($source) : null;
        }

        $url = $this->option('url');
        if (! $url) {
            $this->error('Pasa --url=<raw xml url> o --source=<archivo local>.');

            return null;
        }

        $this->info("Descargando {$url} ...");
        $response = Http::timeout(60)->get($url);
        if (! $response->successful()) {
            $this->error('Descarga falló: HTTP '.$response->status());

            return null;
        }

        $tmpPath = storage_path('app/imports/'.Str::slug($this->argument('code')).'.xml');
        if (! is_dir(dirname($tmpPath))) {
            mkdir(dirname($tmpPath), 0755, true);
        }
        file_put_contents($tmpPath, $response->body());

        return $tmpPath;
    }
}
