<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->t('title'),
            'description' => $this->t('description'),
            'is_premium' => $this->is_premium,
            'events' => EventSummaryResource::collection($this->whenLoaded('events')),
        ];
    }
}
