<?php

namespace App\Billingo\Enums\Document;

enum NotificationStatusEnum: string
{
    case CLOSED = 'closed';
    case DOWNLOADED = 'downloaded';
    case FAILED = 'failed';
    case NONE = 'none';
    case OPENED = 'opened';
    case READED = 'readed';
}
