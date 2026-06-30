<?php

namespace App\Filament\Resources\Crs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CrsTable
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

                TextColumn::make('title_es')
                    ->label('Título (ES)')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('era')
                    ->label('Era')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('placement_confidence')
                    ->label('Certeza ubicación')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'alta'               => 'success',
                        'probable'           => 'info',
                        'debatida'           => 'warning',
                        'tradicion_popular'  => 'warning',
                        'especulativa'       => 'danger',
                        default              => 'gray',
                    }),

                TextColumn::make('review_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved'      => 'success',
                        'needs_review'  => 'warning',
                        'rejected'      => 'danger',
                        default         => 'gray',
                    }),

                TextColumn::make('blocks_count')
                    ->label('Bloques')
                    ->counts('blocks')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('era')
                    ->label('Era')
                    ->options(fn () => \App\Models\ChronologicalReadingSet::distinct()->pluck('era', 'era')->toArray()),

                SelectFilter::make('placement_confidence')
                    ->label('Certeza')
                    ->options([
                        'alta'              => 'Alta',
                        'probable'          => 'Probable',
                        'debatida'          => 'Debatida',
                        'tradicion_popular' => 'Tradición popular',
                        'especulativa'      => 'Especulativa',
                    ]),

                SelectFilter::make('review_status')
                    ->label('Estado de revisión')
                    ->options([
                        'approved'     => 'Aprobado',
                        'needs_review' => 'Necesita revisión',
                        'rejected'     => 'Rechazado',
                    ]),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('sort_key')
            ->striped();
    }
}
