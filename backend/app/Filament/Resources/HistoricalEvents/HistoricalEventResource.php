<?php

namespace App\Filament\Resources\HistoricalEvents;

use App\Filament\Resources\HistoricalEvents\Pages\CreateHistoricalEvent;
use App\Filament\Resources\HistoricalEvents\Pages\EditHistoricalEvent;
use App\Filament\Resources\HistoricalEvents\Pages\ListHistoricalEvents;
use App\Filament\Resources\HistoricalEvents\Schemas\HistoricalEventForm;
use App\Filament\Resources\HistoricalEvents\Tables\HistoricalEventsTable;
use App\Models\HistoricalEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HistoricalEventResource extends Resource
{
    protected static ?string $model = HistoricalEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Eventos piloto (David)';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos piloto';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return HistoricalEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HistoricalEventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricalEvents::route('/'),
            'create' => CreateHistoricalEvent::route('/create'),
            'edit' => EditHistoricalEvent::route('/{record}/edit'),
        ];
    }
}
