<?php

namespace App\Filament\Resources\HistoricalEvents\Schemas;

use App\Enums\CertaintyLevel;
use App\Enums\CharacterStatus;
use App\Filament\Support\TranslationsRepeater;
use App\Models\Character;
use App\Models\Location;
use App\Models\Passage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class HistoricalEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()->columnSpanFull()->tabs([
                    Tab::make('Datos')->schema(self::dataFields()),
                    Tab::make('Texto (ES/EN)')->schema([
                        TranslationsRepeater::make([
                            TextInput::make('title')->label('Título')->required(),
                            Textarea::make('summary')->label('Resumen')->rows(3),
                            Textarea::make('context')->label('Contexto histórico')->rows(6),
                        ]),
                    ]),
                    Tab::make('Pasajes')->schema([self::passagesRepeater()]),
                    Tab::make('Personajes')->schema([self::charactersRepeater()]),
                    Tab::make('Salmos')->schema([self::psalmsRepeater()]),
                    Tab::make('Notas de contexto')->schema([self::notesRepeater()]),
                ]),
            ]);
    }

    private static function dataFields(): array
    {
        return [
            TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)
                ->helperText('Identificador único, ej. "samuel-unge-a-david".')->columnSpan(2),
            Select::make('location_id')->label('Lugar')
                ->options(fn () => Location::with('translations')->get()
                    ->mapWithKeys(fn ($l) => [$l->id => $l->t('name', 'es') ?? $l->slug]))
                ->searchable(),
            Toggle::make('is_premium')->label('Premium'),
            TextInput::make('approximate_date_start')->label('Fecha aprox. (inicio)')
                ->helperText('Texto libre, ej. "c. 1025 a.C."'),
            TextInput::make('approximate_date_end')->label('Fecha aprox. (fin)'),
            Select::make('date_confidence')->label('Certeza de la fecha')->options(CertaintyLevel::class),
            Select::make('certainty_level')->label('Certeza del evento')->options(CertaintyLevel::class),
        ];
    }

    private static function passagesRepeater(): Repeater
    {
        return Repeater::make('eventPassages')
            ->relationship()
            ->label('Pasajes bíblicos')
            ->schema([
                Select::make('passage_id')->label('Pasaje')
                    ->options(fn () => Passage::orderBy('biblical_book_id')->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->reference_label]))
                    ->searchable()->required(),
                Select::make('relationship_type')->label('Tipo')
                    ->options(['primary' => 'Principal', 'parallel' => 'Paralelo', 'background' => 'Trasfondo']),
                Select::make('certainty_level')->label('Certeza')->options(CertaintyLevel::class),
                TranslationsRepeater::make([
                    Textarea::make('explanation')->label('Explicación de la relación')->rows(2),
                ]),
            ])
            ->itemLabel(fn (array $state) => Passage::find($state['passage_id'] ?? null)?->reference_label ?? 'Nuevo pasaje')
            ->orderColumn('sort_order')
            ->collapsible()
            ->addActionLabel('Añadir pasaje');
    }

    private static function charactersRepeater(): Repeater
    {
        return Repeater::make('eventCharacters')
            ->relationship()
            ->label('Personajes en el evento')
            ->columns(3)
            ->schema([
                Select::make('character_id')->label('Personaje')
                    ->options(fn () => Character::with('translations')->get()
                        ->mapWithKeys(fn ($c) => [$c->id => $c->t('name', 'es') ?? $c->slug]))
                    ->searchable()->required(),
                Select::make('status_at_event')->label('Estado')->options(CharacterStatus::class),
                TextInput::make('role_in_event')->label('Rol en el evento'),
            ])
            ->orderColumn('sort_order')
            ->addActionLabel('Añadir personaje');
    }

    private static function psalmsRepeater(): Repeater
    {
        return Repeater::make('psalmConnections')
            ->relationship()
            ->label('Conexiones de Salmos')
            ->schema([
                TextInput::make('psalm_reference')->label('Salmo')->placeholder('Salmo 34')->required(),
                Select::make('passage_id')->label('Pasaje del Salmo')
                    ->options(fn () => Passage::get()->mapWithKeys(fn ($p) => [$p->id => $p->reference_label]))
                    ->searchable(),
                Select::make('certainty_level')->label('Nivel de certeza')->options(CertaintyLevel::class)->required(),
                TranslationsRepeater::make([
                    Textarea::make('reasoning')->label('Evidencia / razonamiento')->rows(2)->required(),
                    Textarea::make('warning_note')->label('Advertencia (si es debatida)')->rows(2),
                ]),
            ])
            ->itemLabel(fn (array $state) => $state['psalm_reference'] ?? 'Nueva conexión')
            ->orderColumn('sort_order')
            ->collapsible()
            ->addActionLabel('Añadir Salmo');
    }

    private static function notesRepeater(): Repeater
    {
        return Repeater::make('contextNotes')
            ->relationship()
            ->label('Notas de contexto')
            ->schema([
                Select::make('type')->label('Tipo')
                    ->options([
                        'historico' => 'Histórico', 'cultural' => 'Cultural', 'geografico' => 'Geográfico',
                        'literario' => 'Literario', 'politico' => 'Político',
                    ])->required(),
                Select::make('certainty_level')->label('Nivel de certeza')->options(CertaintyLevel::class)->required(),
                Repeater::make('sources')->label('Fuentes')->schema([
                    TextInput::make('title')->label('Título'),
                    TextInput::make('ref')->label('Referencia / URL'),
                ])->columns(2)->defaultItems(0)->addActionLabel('Añadir fuente'),
                TranslationsRepeater::make([
                    TextInput::make('title')->label('Título')->required(),
                    Textarea::make('content')->label('Contenido')->rows(4)->required(),
                ]),
            ])
            ->itemLabel(fn (array $state) => $state['type'] ?? 'Nueva nota')
            ->orderColumn('sort_order')
            ->collapsible()
            ->addActionLabel('Añadir nota');
    }
}
