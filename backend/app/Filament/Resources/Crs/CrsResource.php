<?php

namespace App\Filament\Resources\Crs;

use App\Filament\Resources\Crs\Pages\CreateCrs;
use App\Filament\Resources\Crs\Pages\EditCrs;
use App\Filament\Resources\Crs\Pages\ListCrs;
use App\Filament\Resources\Crs\Schemas\CrsForm;
use App\Filament\Resources\Crs\Tables\CrsTable;
use App\Models\ChronologicalReadingSet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CrsResource extends Resource
{
    protected static ?string $model = ChronologicalReadingSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Secuencias CRS';

    protected static ?string $modelLabel = 'secuencia';

    protected static ?string $pluralModelLabel = 'secuencias CRS';

    protected static string|\UnitEnum|null $navigationGroup = 'Canon';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CrsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrsTable::configure($table);
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
