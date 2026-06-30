<?php

namespace App\Filament\Support;

use App\Enums\ReviewStatus;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

/**
 * Repeater reutilizable para editar las filas `*_translations` (ES/EN) de un modelo de contenido.
 * Cada idioma lleva su propio estado de revisión teológica.
 */
class TranslationsRepeater
{
    /**
     * @param  array  $fields  Componentes de formulario propios de la traducción (title, summary, etc.)
     */
    public static function make(array $fields, string $relationship = 'translations'): Repeater
    {
        return Repeater::make($relationship)
            ->relationship()
            ->label('Traducciones por idioma')
            ->schema([
                Select::make('locale')
                    ->label('Idioma')
                    ->options(['es' => 'Español', 'en' => 'English'])
                    ->required()
                    ->distinct()
                    ->fixIndistinctState()
                    ->selectablePlaceholder(false),
                ...$fields,
                Select::make('review_status')
                    ->label('Estado de revisión')
                    ->options(ReviewStatus::class)
                    ->default('draft')
                    ->required(),
            ])
            ->itemLabel(fn (array $state): ?string => match ($state['locale'] ?? null) {
                'es' => 'Español', 'en' => 'English', default => 'Nuevo idioma',
            })
            ->addActionLabel('Añadir idioma')
            ->defaultItems(1)
            ->maxItems(2)
            ->columns(1)
            ->collapsible();
    }
}
