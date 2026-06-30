<?php

namespace App\Filament\Resources\HistoricalEvents\Pages;

use App\Filament\Resources\HistoricalEvents\HistoricalEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHistoricalEvents extends ListRecords
{
    protected static string $resource = HistoricalEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
