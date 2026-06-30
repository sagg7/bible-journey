<?php

namespace App\Filament\Resources\Translations\Schemas;

use App\Enums\LicenseStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Traducción bíblica')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->helperText('RVA1909, WEB, KJV, NVI, NIV, RVR60...')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')->label('Nombre')->required(),
                        Select::make('language')
                            ->label('Idioma')
                            ->options(['es' => 'Español', 'en' => 'English'])
                            ->required(),
                        TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
                        Textarea::make('attribution')->label('Atribución / créditos')->columnSpanFull(),
                    ]),

                Section::make('Licencia y visibilidad del texto')
                    ->description('Controla si la app envía el texto completo o solo la referencia.')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_public_domain')->label('Dominio público'),
                        Select::make('license_status')
                            ->label('Estado de licencia')
                            ->options(LicenseStatus::class)
                            ->default('none')
                            ->required(),
                        Toggle::make('can_display_full_text')
                            ->label('Mostrar texto completo')
                            ->helperText('Si está apagado, la app muestra solo la referencia.'),
                    ]),
            ]);
    }
}
