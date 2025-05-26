<?php

namespace App\Billingo\Enums;

enum OnlinePaymentEnum: string
{
    case EMPTY = '';
    case BARION = 'Barion';
    case SIMPLEPAY = 'SimplePay';
    case NO = 'no';
}
