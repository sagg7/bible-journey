<?php

namespace App\Filament\Resources\Crs;

use App\Filament\Resources\Crs\Pages\CreateCrs;
use App\Filament\Resources\Crs\Pages\EditCrs;
use App\Filament\Resources\Crs\Pages\ListCrs;
use App\Filament\Resources\Crs\Schemas\CrsForm;
use App\Filament\Resources\Crs\Tables\CrsEventsTable;
use App\Models\ChronologicalReadingSet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CrsResource extends Resource
{
    protected static ?string $model = ChronologicalReadingSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Eventos';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return CrsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrsEventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCrs::route('/'),
            'create' => CreateCrs::route('/create'),
            'edit'   => EditCrs::route('/{record}/edit'),
        ];
    }
}
