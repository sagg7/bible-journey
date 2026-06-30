<?php

namespace App\Filament\Resources\Characters\Schemas;

use App\Filament\Support\TranslationsRepeater;
use App\Models\HistoricalEvent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CharacterForm
{
    public static function configure(Schema $schema): Schema
    {
        $eventOptions = fn () => HistoricalEvent::with('translations')->get()
            ->mapWithKeys(fn ($e) => [$e->id => $e->t('title', 'es') ?? $e->slug]);

        return $schema
            ->components([
                Section::make('Personaje')
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)
                            ->helperText('ej. "david", "saul".'),
                        Select::make('first_appearance_event_id')->label('Primera aparición')
                            ->options($eventOptions)->searchable(),
                        Select::make('death_event_id')->label('Evento de su muerte')
                            ->options($eventOptions)->searchable(),
                    ]),

                Section::make('Contenido')
                    ->schema([
                        TranslationsRepeater::make([
                            TextInput::make('name')->label('Nombre')->required(),
                            TextInput::make('role')->label('Rol'),
                            Textarea::make('description')->label('Descripción')->rows(4),
                        ]),
                    ]),
            ]);
    }
}
