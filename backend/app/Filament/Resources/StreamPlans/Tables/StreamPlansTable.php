<?php

namespace App\Filament\Resources\StreamPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StreamPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('profile_id')->label('Perfil'),
                TextColumn::make('locale')->label('Idioma')->badge(),
                TextColumn::make('publication_status')->label('Estado')->badge(),
                ToggleColumn::make('is_test_only')->label('Solo pruebas'),
                TextColumn::make('node_count')->label('Nodos'),
                TextColumn::make('published_at')->label('Publicado')->dateTime()->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
