<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Estado de licencia de una traducción bíblica.
 * Controla, junto con can_display_full_text, si la app envía texto o solo la referencia.
 */
enum LicenseStatus: string implements HasLabel
{
    case None = 'none';          // dominio público o sin gestión
    case Pending = 'pending';    // negociación en curso
    case Licensed = 'licensed';  // licencia obtenida

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Sin licencia / dominio público',
            self::Pending => 'Licencia en gestión',
            self::Licensed => 'Licenciada',
        };
    }
}
