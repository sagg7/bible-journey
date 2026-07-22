<?php

namespace App\Filament\Resources\HistoricalEvents\Tables;

use App\Enums\CertaintyLevel;
use App\Models\EventTranslation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HistoricalEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title_es')
                    ->label('Título (ES)')
                    ->getStateUsing(fn ($record) => $record->translations->firstWhere('locale', 'es')?->title ?? '—')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
                        EventTranslation::query()
                            ->select('title')
                            ->whereColumn('event_translations.historical_event_id', 'historical_events.id')
                            ->where('locale', 'es')
                            ->limit(1),
                        $direction
                    )),

                TextColumn::make('approximate_date_start')
                    ->label('Fecha')
                    ->sortable(),

                TextColumn::make('certainty_level')
                    ->label('Certeza')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_premium')
                    ->label('Premium')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('certainty_level')
                    ->label('Certeza')
                    ->options(CertaintyLevel::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('approximate_date_start')
            ->paginationMode(PaginationMode::Cursor)
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->striped();
    }
}
