<?php

namespace App\Filament\Resources\StreamPlans;

use App\Filament\Resources\StreamPlans\Pages\EditStreamPlan;
use App\Filament\Resources\StreamPlans\Pages\ListStreamPlans;
use App\Filament\Resources\StreamPlans\Schemas\StreamPlanForm;
use App\Filament\Resources\StreamPlans\Tables\StreamPlansTable;
use App\Models\StreamPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StreamPlanResource extends Resource
{
    protected static ?string $model = StreamPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Planes de lectura';

    protected static ?string $modelLabel = 'plan de lectura';

    protected static ?string $pluralModelLabel = 'planes de lectura';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return StreamPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StreamPlansTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
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
            'index' => ListStreamPlans::route('/'),
            'edit' => EditStreamPlan::route('/{record}/edit'),
        ];
    }
}
