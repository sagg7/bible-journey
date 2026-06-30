<?php

namespace App\Filament\Resources\Crs\Pages;

use App\Filament\Resources\Crs\CrsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrs extends ListRecords
{
    protected static string $resource = CrsResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
