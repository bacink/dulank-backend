<?php

namespace App\Enums;

/**
 * Kanal akuisisi customer: Online (store) atau Offline (sales).
 */
enum CustomerChannel: string
{
    case Online = 'Online';
    case Offline = 'Offline';

    public function label(): string
    {
        return $this->value;
    }
}
