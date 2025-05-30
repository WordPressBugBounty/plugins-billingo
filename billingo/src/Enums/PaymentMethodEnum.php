<?php

namespace App\Billingo\Enums;

enum PaymentMethodEnum: string
{
    case ARUHITEL = 'aruhitel';
    case BANKCARD = 'bankcard';
    case BARION = 'barion';
    case BARTER = 'barter';
    case CASH = 'cash';
    case CASH_ON_DELIVERY = 'cash_on_delivery';
    case COUPON = 'coupon';
    case ELORE_UTALAS = 'elore_utalas';
    case EP_KARTYA = 'ep_kartya';
    case KOMPENZACIO = 'kompenzacio';
    case LEVONAS = 'levonas';
    case ONLINE_BANKCARD = 'online_bankcard';
    case PAYLIKE = 'paylike';
    case PAYONEER = 'payoneer';
    case PAYPAL = 'paypal';
    case PAYPAL_UTOLAG = 'paypal_utolag';
    case PAYU = 'payu';
    case PICK_PACK_PONT = 'pick_pack_pont';
    case POSTAI_CSEKK = 'postai_csekk';
    case POSTAUTALVANY = 'postautalvany';
    case SKRILL = 'skrill';
    case SZEP_CARD = 'szep_card';
    case TRANSFERWISE = 'transferwise';
    case UPWORK = 'upwork';
    case UTALVANY = 'utalvany';
    case VALTO = 'valto';
    case WIRE_TRANSFER = 'wire_transfer';

    public function getReadableText(): string
    {
        return match ($this) {
            self::ARUHITEL => trans('payment_method.aruhitel'),
            self::BANKCARD => trans('payment_method.bankcard'),
            self::BARION => trans('payment_method.barion'),
            self::BARTER => trans('payment_method.barter'),
            self::CASH => trans('payment_method.cash'),
            self::CASH_ON_DELIVERY => trans('payment_method.cash_on_delivery'),
            self::COUPON => trans('payment_method.coupon'),
            self::ELORE_UTALAS => trans('payment_method.elore_utalas'),
            self::EP_KARTYA => trans('payment_method.ep_kartya'),
            self::KOMPENZACIO => trans('payment_method.kompenzacio'),
            self::LEVONAS => trans('payment_method.levonas'),
            self::ONLINE_BANKCARD => trans('payment_method.online_bankcard'),
            self::PAYLIKE => trans('payment_method.paylike'),
            self::PAYONEER => trans('payment_method.payoneer'),
            self::PAYPAL => trans('payment_method.paypal'),
            self::PAYPAL_UTOLAG => trans('payment_method.paypal_utolag'),
            self::PAYU => trans('payment_method.payu'),
            self::PICK_PACK_PONT => trans('payment_method.pick_pack_pont'),
            self::POSTAI_CSEKK => trans('payment_method.postai_csekk'),
            self::POSTAUTALVANY => trans('payment_method.postautalvany'),
            self::SKRILL => trans('payment_method.skrill'),
            self::SZEP_CARD => trans('payment_method.szep_card'),
            self::TRANSFERWISE => trans('payment_method.transferwise'),
            self::UPWORK => trans('payment_method.upwork'),
            self::UTALVANY => trans('payment_method.utalvany'),
            self::VALTO => trans('payment_method.valto'),
            self::WIRE_TRANSFER => trans('payment_method.wire_transfer'),
        };
    }

    public static function fromWoocommerce(string $value): ?self
    {
        return match($value){
            'bacs' => self::WIRE_TRANSFER,
            'cheque' => self::POSTAI_CSEKK,
            'cod' => self::CASH_ON_DELIVERY,
            // Szépkártya variations
            'szepcard', 'szep_card', 'woocommerce-gateway-szepcard', 'simplepay_szepcard' => self::SZEP_CARD,
            // Barion
            'barion', 'woocommerce-gateway-barion' => self::BARION,
            // PayPal variations
            'paypal', 'woocommerce-gateway-paypal', 'paypal_express' => self::PAYPAL,
            // PayU
            'payu', 'woocommerce-gateway-payu' => self::PAYU,
            // Online bankkártya / Bankcard
            'stripe', 'woocommerce-gateway-stripe', 'card', 'credit_card', 'bankcard' , 'woocommerce_payments' => self::ONLINE_BANKCARD,
            // Készpénz
            'cash', 'cash_payment' => self::CASH,
            default => null,
        };
    }
}
