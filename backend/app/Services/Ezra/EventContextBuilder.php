<?php

namespace App\Services\Ezra;

use App\Models\HistoricalEvent;

/**
 * Construye el bloque de contexto que ancla a Ezra al contenido preconstruido del evento.
 * Ezra solo debe responder con base en este contexto.
 */
class EventContextBuilder
{
    public static function build(HistoricalEvent $event, string $locale): string
    {
        $event->loadMissing([
            'translations', 'location.translations',
            'eventPassages.translations', 'eventPassages.passage.book',
            'eventCharacters.character.translations',
            'psalmConnections.translations',
            'contextNotes.translations',
        ]);

        $prev = app()->getLocale();
        app()->setLocale($locale);

        $lines = [];
        $lines[] = 'EVENTO: '.$event->t('title');
        if ($event->approximate_date_start) {
            $lines[] = 'FECHA APROXIMADA: '.$event->approximate_date_start.' (certeza: '.($event->date_confidence?->getLabel() ?? 'n/d').')';
        }
        $lines[] = 'CERTEZA DEL EVENTO: '.($event->certainty_level?->getLabel() ?? 'n/d');
        if ($event->location) {
            $lines[] = 'LUGAR: '.$event->location->t('name').' ('.($event->location->modern_equivalent ?? '').')';
        }
        $lines[] = '';
        $lines[] = 'RESUMEN: '.$event->t('summary');
        if ($event->t('context')) {
            $lines[] = 'CONTEXTO: '.$event->t('context');
        }

        if ($event->eventPassages->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'PASAJES BÍBLICOS:';
            foreach ($event->eventPassages as $ep) {
                $line = '- '.$ep->passage->reference_label;
                if ($ep->t('explanation')) {
                    $line .= ' — '.$ep->t('explanation');
                }
                $lines[] = $line;
            }
        }

        if ($event->eventCharacters->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'PERSONAJES:';
            foreach ($event->eventCharacters as $ec) {
                $lines[] = '- '.$ec->character->t('name')
                    .($ec->status_at_event ? ' (estado: '.$ec->status_at_event->getLabel().')' : '')
                    .($ec->role_in_event ? ' — '.$ec->role_in_event : '');
            }
        }

        if ($event->psalmConnections->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'CONEXIONES DE SALMOS:';
            foreach ($event->psalmConnections as $pc) {
                $line = '- '.$pc->psalm_reference.' [certeza: '.($pc->certainty_level?->getLabel() ?? 'n/d').']: '.$pc->t('reasoning');
                if ($pc->t('warning_note')) {
                    $line .= ' ADVERTENCIA: '.$pc->t('warning_note');
                }
                $lines[] = $line;
            }
        }

        if ($event->contextNotes->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'NOTAS DE CONTEXTO:';
            foreach ($event->contextNotes as $note) {
                $lines[] = '- ['.$note->type.', certeza: '.($note->certainty_level?->getLabel() ?? 'n/d').'] '
                    .$note->t('title').': '.$note->t('content');
            }
        }

        app()->setLocale($prev);

        return implode("\n", $lines);
    }
}
