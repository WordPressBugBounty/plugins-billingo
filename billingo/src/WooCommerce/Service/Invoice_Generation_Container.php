<?php

namespace App\Billingo\WooCommerce\Service;

use App\Billingo\WooCommerce\Controllers\Billingo_Controller;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use WC_Order;
use Exception;

class Invoice_Generation_Container
{
    private readonly WC_Order $order;
    private readonly string $status;
    private readonly string $activationStatus;
    private readonly string $autoStornoStatus;
    private readonly Billingo_Document_Generator $documentGenerator;
    private readonly Billingo_Controller $controller;
    private readonly Billingo_Repositroy $repositroy;

    /**
     * @param int $orderId
     * @throws Exception
     */
    public function __construct(int $orderId)
    {
        // 1) Rendelés lekérdezése lokálisan
        $order = wc_get_order($orderId);

        // 2) Ellenőrzés – itt még NINCS property-nek adva
        if ( ! ($order instanceof WC_Order) ) {
            throw new Exception('A rendelés nem található vagy érvénytelen.');
        }

        // 3) Innentől biztos, hogy WC_Order, most már mehet a readonly property-be
        $this->order = $order;

        // 4) További readonly property-k inicializálása
        $this->status           = $this->order->get_status();
        $this->activationStatus = str_replace('wc-', '', (string) get_option('wc_billingo_auto_state'));
        $this->autoStornoStatus = str_replace('wc-', '', (string) get_option('wc_billingo_auto_storno'));

        $this->documentGenerator = new Billingo_Document_Generator($orderId);
        $this->controller        = new Billingo_Controller($orderId);
        $this->repositroy        = new Billingo_Repositroy();
    }

    public function getOrder(): WC_Order
    {
        return $this->order;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getActivationStatus(): string
    {
        return $this->activationStatus;
    }

    public function getAutoStornoStatus(): string
    {
        return $this->autoStornoStatus;
    }

    public function getDocumentGenerator(): Billingo_Document_Generator
    {
        return $this->documentGenerator;
    }

    public function getController(): Billingo_Controller
    {
        return $this->controller;
    }

    public function getRepositroy(): Billingo_Repositroy
    {
        return $this->repositroy;
    }
}
