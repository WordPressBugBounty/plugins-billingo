<?php

namespace App\Billingo\Enums;

enum OnlinePaymentEnum: string
{
    case EMPTY = '';
    case BARION = 'Barion';
    case SIMPLEPAY = 'SimplePay';
    case online_bankcard = 'online_payment';
    case online_payment = 'Stripe';
    case NO = 'no';
}
