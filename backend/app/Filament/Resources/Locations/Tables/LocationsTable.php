<?php

namespace App\Filament\Resources\Locations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('name_es')
                    ->label('Nombre (ES)')
                    ->getStateUsing(fn ($record) => $record->translations->firstWhere('locale', 'es')?->name ?? '—'),
                TextColumn::make('modern_equivalent')->label('Equivalente moderno'),
                TextColumn::make('certainty_level')->label('Certeza')->badge(),
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
