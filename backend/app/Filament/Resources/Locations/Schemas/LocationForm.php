<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\CertaintyLevel;
use App\Filament\Support\TranslationsRepeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lugar')
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
                        TextInput::make('modern_equivalent')->label('Equivalente moderno'),
                        TextInput::make('latitude')->label('Latitud')->numeric(),
                        TextInput::make('longitude')->label('Longitud')->numeric(),
                        Select::make('certainty_level')->label('Certeza de ubicación')->options(CertaintyLevel::class),
                    ]),

                Section::make('Contenido')
                    ->schema([
                        TranslationsRepeater::make([
                            TextInput::make('name')->label('Nombre')->required(),
                            Textarea::make('notes')->label('Notas')->rows(3),
                        ]),
                    ]),
            ]);
    }
}
