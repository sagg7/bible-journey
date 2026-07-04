<?php

namespace App\Filament\Resources\Institutions;

use App\Filament\Resources\Institutions\Pages\CreateInstitution;
use App\Filament\Resources\Institutions\Pages\EditInstitution;
use App\Filament\Resources\Institutions\Pages\ListInstitutions;
use App\Filament\Resources\Institutions\Schemas\InstitutionForm;
use App\Filament\Resources\Institutions\Tables\InstitutionsTable;
use App\Models\Institution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstitutionResource extends Resource
{
    protected static ?string $model = Institution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Instituciones';

    protected static ?string $modelLabel = 'institución';

    protected static ?string $pluralModelLabel = 'instituciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Instituciones';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return InstitutionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstitutionsTable::configure($table);
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
            'index' => ListInstitutions::route('/'),
            'create' => CreateInstitution::route('/create'),
            'edit' => EditInstitution::route('/{record}/edit'),
        ];
    }
}
