<?php

namespace App\Filament\Resources\Translations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TranslationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label('Código')->badge()->searchable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('language')->label('Idioma')->badge(),
                TextColumn::make('license_status')->label('Licencia')->badge(),
                IconColumn::make('is_public_domain')->label('Dom. público')->boolean(),
                IconColumn::make('can_display_full_text')->label('Texto completo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
