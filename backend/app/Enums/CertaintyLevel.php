<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Nivel de certeza — campo de primera clase del producto.
 * Se renderiza como badge en el panel admin y en la app.
 * Ver docs/certainty-levels.md
 */
enum CertaintyLevel: string implements HasLabel, HasColor
{
    case Alta = 'alta';
    case Probable = 'probable';
    case Debatida = 'debatida';
    case TradicionPopular = 'tradicion_popular';
    case Especulativa = 'especulativa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Alta => 'Alta confianza',
            self::Probable => 'Probable',
            self::Debatida => 'Debatida',
            self::TradicionPopular => 'Tradición popular',
            self::Especulativa => 'Especulativa',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Alta => 'High confidence',
            self::Probable => 'Probable',
            self::Debatida => 'Debated',
            self::TradicionPopular => 'Popular tradition',
            self::Especulativa => 'Speculative',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Alta => 'success',
            self::Probable => 'info',
            self::Debatida => 'warning',
            self::TradicionPopular => 'gray',
            self::Especulativa => 'danger',
        };
    }
}
