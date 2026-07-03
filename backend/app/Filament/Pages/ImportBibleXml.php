<?php

namespace App\Filament\Pages;

use App\Models\Translation;
use App\Services\Bible\BibleXmlImportException;
use App\Services\Bible\BibleXmlImportService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use UnitEnum;

/**
 * Web UI wrapper around App\Services\Bible\BibleXmlImportService, for admins
 * without SSH/CLI access. Accepts either an uploaded .xml file or a raw URL,
 * same format as `bible:import-xml` (Beblia Holy-Bible-XML-Format).
 */
class ImportBibleXml extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Importar texto bíblico (XML)';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected string $view = 'filament.pages.import-bible-xml';

    /** @var array<string,mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Importar traducción desde XML')
                    ->description('Formato Beblia Holy-Bible-XML-Format (<bible><testament><book number><chapter number><verse number>). El comando rechaza traducciones sin dominio público ni licencia.')
                    ->schema([
                        Select::make('translation_id')
                            ->label('Traducción')
                            ->options(fn () => Translation::query()
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn (Translation $t) => [
                                    $t->id => "{$t->code} — {$t->name} ({$t->license_status->getLabel()})",
                                ]))
                            ->required()
                            ->native(false),
                        FileUpload::make('xml_file')
                            ->label('Archivo XML')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['text/xml', 'application/xml'])
                            ->helperText('O dejá esto vacío y completá la URL de abajo.'),
                        TextInput::make('url')
                            ->label('URL del XML (alternativa al archivo)')
                            ->url()
                            ->helperText('Ej: raw.githubusercontent.com/.../archivo.xml — se usa solo si no subiste un archivo.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function import(BibleXmlImportService $importer): void
    {
        $state = $this->form->getState();

        $translation = Translation::find($state['translation_id']);
        if (! $translation) {
            Notification::make()->title('Traducción no encontrada')->danger()->send();

            return;
        }

        $xmlPath = null;
        $sourceUrl = null;

        try {
            if (! empty($state['xml_file'])) {
                $xmlPath = storage_path('app/private/'.$state['xml_file']);
            } elseif (! empty($state['url'])) {
                $sourceUrl = $state['url'];
                $response = Http::timeout(60)->get($sourceUrl);
                if (! $response->successful()) {
                    Notification::make()->title('Descarga falló: HTTP '.$response->status())->danger()->send();

                    return;
                }
                $xmlPath = storage_path('app/imports/'.$translation->code.'.xml');
                if (! is_dir(dirname($xmlPath))) {
                    mkdir(dirname($xmlPath), 0755, true);
                }
                file_put_contents($xmlPath, $response->body());
            } else {
                Notification::make()->title('Subí un archivo XML o completá una URL.')->danger()->send();

                return;
            }

            $stats = $importer->import($translation, $xmlPath, $sourceUrl);

            Notification::make()
                ->title('Importación completa')
                ->body("Libros={$stats['books']} Capítulos={$stats['chapters']} Versículos={$stats['verses']}")
                ->success()
                ->send();

            $this->form->fill();
        } catch (BibleXmlImportException $e) {
            Notification::make()->title('Importación bloqueada')->body($e->getMessage())->danger()->send();
        }
    }
}
