<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de revisión teológica de una traducción de contenido (por idioma).
 */
enum ReviewStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::InReview => 'En revisión',
            self::Approved => 'Aprobado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Approved => 'success',
        };
    }
}
