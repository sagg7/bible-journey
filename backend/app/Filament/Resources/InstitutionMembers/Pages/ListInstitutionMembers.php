<?php

namespace App\Filament\Resources\InstitutionMembers\Pages;

use App\Filament\Resources\InstitutionMembers\InstitutionMemberResource;
use Filament\Resources\Pages\ListRecords;

class ListInstitutionMembers extends ListRecords
{
    protected static string $resource = InstitutionMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
