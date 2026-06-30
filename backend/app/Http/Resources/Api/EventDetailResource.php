<?php

namespace App\Http\Resources\Api;

use App\Support\BibleTranslationResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $request->attributes->get('bibleTranslation');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->t('title'),
            'summary' => $this->t('summary'),
            'context' => $this->t('context'),
            'approximate_date' => $this->approximate_date_start,
            'certainty_level' => $this->certainty_level?->value,
            'certainty_label' => $this->certainty_level?->getLabel(),
            'is_premium' => $this->is_premium,

            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'name' => $this->location->t('name'),
                'modern_equivalent' => $this->location->modern_equivalent,
                'latitude' => $this->location->latitude,
                'longitude' => $this->location->longitude,
                'certainty_level' => $this->location->certainty_level?->value,
            ] : null),

            'passages' => $this->eventPassages->map(fn ($ep) => array_merge(
                BibleTranslationResolver::passagePayload($ep->passage, $translation),
                [
                    'relationship_type' => $ep->relationship_type,
                    'certainty_level' => $ep->certainty_level?->value,
                    'certainty_label' => $ep->certainty_level?->getLabel(),
                    'explanation' => $ep->t('explanation'),
                ]
            )),

            'characters' => $this->eventCharacters->map(fn ($ec) => [
                'slug' => $ec->character->slug,
                'name' => $ec->character->t('name'),
                'role' => $ec->character->t('role'),
                'role_in_event' => $ec->role_in_event,
                'status_at_event' => $ec->status_at_event?->value,
                'status_label' => $ec->status_at_event?->getLabel(),
            ]),

            'psalm_connections' => $this->psalmConnections->map(fn ($pc) => array_merge(
                [
                    'psalm_reference' => $pc->psalm_reference,
                    'certainty_level' => $pc->certainty_level?->value,
                    'certainty_label' => $pc->certainty_level?->getLabel(),
                    'reasoning' => $pc->t('reasoning'),
                    'warning_note' => $pc->t('warning_note'),
                ],
                $pc->passage ? ['passage' => BibleTranslationResolver::passagePayload($pc->passage, $translation)] : []
            )),

            'context_notes' => $this->contextNotes->map(fn ($note) => [
                'type' => $note->type,
                'certainty_level' => $note->certainty_level?->value,
                'certainty_label' => $note->certainty_level?->getLabel(),
                'title' => $note->t('title'),
                'content' => $note->t('content'),
                'sources' => $note->sources,
            ]),
        ];
    }
}
