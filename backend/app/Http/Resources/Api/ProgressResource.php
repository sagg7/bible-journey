<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completed = $this->completed_events ?? [];

        return [
            'route_id' => $this->route_id,
            'route_slug' => $this->whenLoaded('route', fn () => $this->route->slug),
            'current_event_id' => $this->current_event_id,
            'completed_events' => $completed,
            'completed_count' => count($completed),
            'streak_count' => $this->streak_count,
            'last_activity_date' => $this->last_activity_date?->toDateString(),
        ];
    }
}
