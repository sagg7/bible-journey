<?php

namespace App\Filament\Resources\InstitutionMembers;

use App\Filament\Resources\InstitutionMembers\Pages\ListInstitutionMembers;
use App\Filament\Resources\InstitutionMembers\Tables\InstitutionMembersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstitutionMemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Miembros';

    protected static ?string $modelLabel = 'miembro';

    protected static ?string $pluralModelLabel = 'miembros';

    protected static string|\UnitEnum|null $navigationGroup = 'Instituciones';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_admin || $user?->is_institution_admin);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->whereNotNull('institution_id');

        $user = auth()->user();
        if (! $user?->is_admin) {
            $query->where('institution_id', $user?->institution_id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return InstitutionMembersTable::configure($table);
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
            'index' => ListInstitutionMembers::route('/'),
        ];
    }
}
