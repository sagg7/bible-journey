<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'language' => $this->language,
            'can_display_full_text' => $this->can_display_full_text,
            'is_public_domain' => $this->is_public_domain,
            'attribution' => $this->attribution,
        ];
    }
}
