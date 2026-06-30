<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'sort_order' => $this->whenPivotLoaded('route_events', fn () => $this->pivot->sort_order),
            'title' => $this->t('title'),
            'summary' => $this->t('summary'),
            'approximate_date' => $this->approximate_date_start,
            'certainty_level' => $this->certainty_level?->value,
            'certainty_label' => $this->certainty_level?->getLabel(),
            'is_premium' => $this->is_premium,
            'locked' => $this->is_premium && ! self::userIsPremium($request),
        ];
    }

    public static function userIsPremium(Request $request): bool
    {
        return in_array(optional($request->user())->subscription_status, ['premium', 'active'], true);
    }
}
