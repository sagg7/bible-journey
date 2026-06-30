<?php

namespace App\Filament\Resources\Crs\Pages;

use App\Filament\Resources\Crs\CrsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrs extends EditRecord
{
    protected static string $resource = CrsResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
