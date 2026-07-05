<?php

namespace App\Filament\Resources\Institutions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstitutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Institución')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required(),
                        TextInput::make('seats')
                            ->label('Asientos')
                            ->numeric()
                            ->required()
                            ->minValue(fn () => (int) config('services.stripe_institution_min_seats'))
                            ->default(fn () => (int) config('services.stripe_institution_min_seats')),
                    ]),
            ]);
    }
}
