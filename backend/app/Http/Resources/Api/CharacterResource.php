<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'role' => $this->t('role'),
            'description' => $this->t('description'),
            'first_appearance' => $this->whenLoaded('firstAppearanceEvent', fn () => $this->firstAppearanceEvent ? [
                'slug' => $this->firstAppearanceEvent->slug,
                'title' => $this->firstAppearanceEvent->t('title'),
            ] : null),
            'death_event' => $this->whenLoaded('deathEvent', fn () => $this->deathEvent ? [
                'slug' => $this->deathEvent->slug,
                'title' => $this->deathEvent->t('title'),
            ] : null),
        ];
    }
}
