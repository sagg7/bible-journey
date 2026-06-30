<?php

namespace App\Filament\Resources\HistoricalEvents\Pages;

use App\Filament\Resources\HistoricalEvents\HistoricalEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHistoricalEvent extends CreateRecord
{
    protected static string $resource = HistoricalEventResource::class;
}
