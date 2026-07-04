<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Usuario')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required(),
                        TextInput::make('email')->label('Correo')->email()->required()->unique(ignoreRecord: true),
                        TextInput::make('password')->label('Contraseña')->password()->revealable()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Dejar en blanco para no cambiarla.'),
                        TextInput::make('subscription_status')->label('Estado de suscripción')->default('free'),
                    ]),

                Section::make('Acceso')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_test_access')->label('Acceso a versiones de prueba'),
                        Toggle::make('is_admin')->label('Administrador'),
                    ]),
            ]);
    }
}
