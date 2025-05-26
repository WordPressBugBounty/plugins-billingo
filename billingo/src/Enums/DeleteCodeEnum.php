<?php

namespace App\Billingo\Enums;

enum DeleteCodeEnum: string
{
    case DELETE_CODE = 'Fejlesztés alatt';

    public function getReadableText(): string
    {
        return match ($this) {
            self::DELETE_CODE => 'Fejlesztés alatt',
        };
    }
}
