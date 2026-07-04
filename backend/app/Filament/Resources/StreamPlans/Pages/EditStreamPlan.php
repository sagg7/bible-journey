<?php

namespace App\Filament\Resources\StreamPlans\Pages;

use App\Filament\Resources\StreamPlans\StreamPlanResource;
use Filament\Resources\Pages\EditRecord;

class EditStreamPlan extends EditRecord
{
    protected static string $resource = StreamPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
