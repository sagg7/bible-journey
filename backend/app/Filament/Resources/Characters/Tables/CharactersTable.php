<?php

namespace App\Filament\Resources\Characters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CharactersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('name_es')
                    ->label('Nombre (ES)')
                    ->getStateUsing(fn ($record) => $record->translations->firstWhere('locale', 'es')?->name ?? '—'),
                TextColumn::make('role_es')
                    ->label('Rol (ES)')
                    ->getStateUsing(fn ($record) => $record->translations->firstWhere('locale', 'es')?->role ?? '—'),
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
