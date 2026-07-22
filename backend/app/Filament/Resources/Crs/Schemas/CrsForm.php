<?php

namespace App\Filament\Resources\Crs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CrsForm
{
    private const CONFIDENCE_OPTIONS = [
        'alta'              => 'Alta',
        'probable'          => 'Probable',
        'debatida'          => 'Debatida',
        'tradicion_popular' => 'Tradición popular',
        'especulativa'      => 'Especulativa',
    ];

    private const STATUS_OPTIONS = [
        'needs_review' => 'Necesita revisión',
        'approved'     => 'Aprobado',
        'rejected'     => 'Rechazado',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Identificación')->schema(self::identificationFields()),
                Tab::make('Títulos')->schema(self::titleFields()),
                Tab::make('Certeza y estado')->schema(self::certaintyFields()),
                Tab::make('Textos editoriales')->schema(self::editorialFields()),
            ]),
        ]);
    }

    private static function identificationFields(): array
    {
        return [
            TextInput::make('source_map')
                ->label('Fuente (source_map)')
                ->required()
                ->helperText('Ej: 1 Sam 16:1-13 | Sal 78:70-72')
                ->columnSpan(2),

            TextInput::make('era')
                ->label('Era (nombre para mostrar)')
                ->required()
                ->placeholder('Monarquía unida'),

            TextInput::make('era_slug')
                ->label('Era (slug)')
                ->required()
                ->placeholder('monarquia_unida'),

            TextInput::make('approximate_date_start')
                ->label('Fecha aprox. (inicio)')
                ->placeholder('c. 1025 a.C.'),

            TextInput::make('approximate_date_end')
                ->label('Fecha aprox. (fin)')
                ->placeholder('c. 1010 a.C.'),

            Select::make('date_confidence')
                ->label('Certeza de la fecha')
                ->options(self::CONFIDENCE_OPTIONS),

            TextInput::make('sort_key')
                ->label('Clave de orden (sort_key)')
                ->numeric()
                ->required(),

            TextInput::make('canon_profile')
                ->label('Perfil canónico')
                ->placeholder('cautious_default'),
        ];
    }

    private static function titleFields(): array
    {
        return [
            TextInput::make('title_es')
                ->label('Título (español)')
                ->required()
                ->columnSpan(2),

            TextInput::make('title_en')
                ->label('Título (inglés)')
                ->columnSpan(2),
        ];
    }

    private static function certaintyFields(): array
    {
        return [
            Select::make('placement_confidence')
                ->label('Certeza de ubicación cronológica')
                ->options(self::CONFIDENCE_OPTIONS)
                ->required(),

            Select::make('event_confidence')
                ->label('Certeza del evento histórico')
                ->options(self::CONFIDENCE_OPTIONS)
                ->required(),

            Select::make('relation_confidence')
                ->label('Certeza de la relación')
                ->options(self::CONFIDENCE_OPTIONS),

            Select::make('review_status')
                ->label('Estado de revisión')
                ->options(self::STATUS_OPTIONS)
                ->required(),

            TextInput::make('editorial_version')
                ->label('Versión editorial')
                ->placeholder('1.0'),
        ];
    }

    private static function editorialFields(): array
    {
        return [
            Textarea::make('editorial_note')
                ->label('Nota editorial (¿Por qué está aquí?)')
                ->rows(4)
                ->columnSpan(2),

            Textarea::make('narrative_flow_message_es')
                ->label('Mensaje de flujo narrativo (ES)')
                ->helperText('Mostrado en Study Mode → Resumen → Por qué importa en la historia')
                ->rows(4)
                ->columnSpan(2),

            Textarea::make('transition_copy_es')
                ->label('Texto de transición (ES)')
                ->rows(2)
                ->columnSpan(2),
        ];
    }
}
