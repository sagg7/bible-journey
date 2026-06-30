<?php

namespace App\Filament\Resources\HistoricalEvents\Pages;

use App\Filament\Resources\HistoricalEvents\HistoricalEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHistoricalEvent extends EditRecord
{
    protected static string $resource = HistoricalEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
