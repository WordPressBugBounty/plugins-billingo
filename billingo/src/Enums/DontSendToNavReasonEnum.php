<?php

namespace App\Billingo\Enums;

enum DontSendToNavReasonEnum: string
{
    case DONT_SEND_FOREIGN = 'dont_send_foreign';
    case DONT_SEND_OSS = 'dont_send_oss';
    case DONT_SEND_OTHER = 'dont_send_other';
}
