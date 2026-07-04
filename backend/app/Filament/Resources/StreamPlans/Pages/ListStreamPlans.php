<?php

namespace App\Filament\Resources\StreamPlans\Pages;

use App\Filament\Resources\StreamPlans\StreamPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListStreamPlans extends ListRecords
{
    protected static string $resource = StreamPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
