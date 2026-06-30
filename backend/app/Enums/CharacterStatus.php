<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de un personaje en el momento de un evento.
 */
enum CharacterStatus: string implements HasLabel, HasColor
{
    case Vivo = 'vivo';
    case Muerto = 'muerto';
    case Activo = 'activo';
    case FueraDeEscena = 'fuera_de_escena';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vivo => 'Vivo',
            self::Muerto => 'Muerto',
            self::Activo => 'Activo',
            self::FueraDeEscena => 'Fuera de escena',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vivo => 'success',
            self::Activo => 'info',
            self::FueraDeEscena => 'gray',
            self::Muerto => 'danger',
        };
    }
}
