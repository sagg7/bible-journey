<?php

namespace App\Filament\Resources\HistoricalEvents\Tables;

use App\Enums\CertaintyLevel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HistoricalEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('title_es')
                    ->label('Título (ES)')
                    ->getStateUsing(fn ($record) => $record->translations->firstWhere('locale', 'es')?->title ?? '—'),
                TextColumn::make('approximate_date_start')->label('Fecha'),
                TextColumn::make('certainty_level')->label('Certeza')->badge(),
                IconColumn::make('is_premium')->label('Premium')->boolean(),
            ])
            ->filters([
                SelectFilter::make('certainty_level')->label('Certeza')->options(CertaintyLevel::class),
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
