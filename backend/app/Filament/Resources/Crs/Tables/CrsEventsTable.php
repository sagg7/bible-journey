<?php

namespace App\Filament\Resources\Crs\Tables;

use App\Models\ChronologicalReadingSet;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CrsEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_map')
                    ->label('Fuente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->width('160px'),

                TextColumn::make('estimated_date')
                    ->label('Fecha estimada')
                    ->getStateUsing(fn (ChronologicalReadingSet $record): ?string => $record->approximate_date_start)
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderByRaw('approximate_year_start IS NULL '.($direction === 'asc' ? 'DESC' : 'ASC'))
                        ->orderBy('approximate_year_start', $direction)
                        ->orderBy('id', $direction))
                    ->placeholder('-')
                    ->width('150px'),

                TextColumn::make('title_es')
                    ->label('Titulo (ES)')
                    ->searchable()
                    ->sortable()
                    ->limit(64)
                    ->wrap(),

                TextColumn::make('era')
                    ->label('Era')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('placement_confidence')
                    ->label('Certeza')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'alta' => 'success',
                        'probable' => 'info',
                        'debatida' => 'warning',
                        'tradicion_popular' => 'warning',
                        'especulativa' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('review_status')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'needs_review' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                ToggleColumn::make('is_premium')
                    ->label('Premium')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('era')
                    ->label('Era')
                    ->options(fn () => ChronologicalReadingSet::query()
                        ->distinct()
                        ->orderBy('era')
                        ->pluck('era', 'era')
                        ->toArray()),

                SelectFilter::make('placement_confidence')
                    ->label('Certeza')
                    ->options([
                        'alta' => 'Alta',
                        'probable' => 'Probable',
                        'debatida' => 'Debatida',
                        'tradicion_popular' => 'Tradicion popular',
                        'especulativa' => 'Especulativa',
                    ]),

                SelectFilter::make('review_status')
                    ->label('Estado de revision')
                    ->options([
                        'approved' => 'Aprobado',
                        'needs_review' => 'Necesita revision',
                        'rejected' => 'Rechazado',
                    ]),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markFree')
                        ->label('Marcar como gratis')
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->action(fn (Collection $records) => $records->each->update(['is_premium' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markPremium')
                        ->label('Marcar como premium')
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->action(fn (Collection $records) => $records->each->update(['is_premium' => true]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort(fn (Builder $query, string $direction) => $query
                ->orderBy('sort_key', $direction)
                ->orderBy('id', $direction))
            ->paginationMode(PaginationMode::Cursor)
            ->paginationPageOptions([25, 50])
            ->defaultPaginationPageOption(50)
            ->striped();
    }
}
