<?php

namespace App\Filament\Resources\StreamPlans\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StreamPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Acceso')
                    ->schema([
                        Toggle::make('is_test_only')->label('Solo para usuarios con acceso a pruebas')
                            ->helperText('El estado de publicación y la compilación del plan se gestionan por CLI; aquí solo se controla la visibilidad para probadores.'),
                    ]),
            ]);
    }
}
