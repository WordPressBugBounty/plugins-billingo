<?php

namespace App\Billingo\Enums\Partner;

enum ShowTypeEnum: string
{
    case SHOW_ALL = 'show_all';
    case SHOW_INCOMING = 'show_incoming';
    case SHOW_OUTGOING = 'show_outgoing';
}
