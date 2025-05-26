<?php

namespace App\Billingo\Enums;

enum RoundEnum: string
{
    case FIVE = 'five';
    case NONE = 'none';
    case ONE = 'one';
    case TEN = 'ten';

    public function getReadableText(): string
    {
        return match ($this) {
            self::FIVE => trans('round.five'),
            self::NONE => trans('round.none'),
            self::ONE => trans('round.one'),
            self::TEN => trans('round.ten'),
        };
    }
}
