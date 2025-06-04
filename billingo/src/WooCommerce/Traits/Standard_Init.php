<?php

namespace App\Billingo\WooCommerce\Traits;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Service\BillingoClient;
use App\Billingo\WooCommerce\Controllers\Billingo_Controller;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use App\Billingo\WooCommerce\Service\Billingo_Document_Generator;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use App\Billingo\WooCommerce\Service\Invoice_Generation_Container;
use Symfony\Component\HttpFoundation\Response;
use WC_Order;

trait Standard_Init
{

    public static function init_hooks(): void
    {
        foreach (wc_get_order_statuses() as $status => $name) {
            $status = str_replace('wc-', '', $status);
            add_action('woocommerce_order_status_' . $status, [self::class, 'on_order_state_change'], 10);
        }
        add_action('woocommerce_thankyou', [self::class, 'should_proforma_generate'], 10, 1);
        add_action('wp_ajax_wc_billingo_generate_invoice', [self::class, 'ajax_generateInvoice']);
        add_action('wp_ajax_wc_billingo_storno_invoice', [self::class, 'ajax_stornoInvoice']);
        add_action('woocommerce_email_before_order_table',
            [self::class, 'action_woocommerce_email_before_order_table'], 20, 4);
    }

    /**
     * Processes automatic document operations
     *
     * @param integer $order_id ID of the order that is linked to the document
     */
    public static function on_order_state_change($order_id): void
    {
        $invoice_generation_data = self::collect_invoice_generation_data($order_id);

        if (self::should_document_generate($invoice_generation_data)) {
            Billingo_Logger::startDocumentum();

            $document = $invoice_generation_data->getDocumentGenerator()->get();
            if (!is_null($document)) {
                $invoice_generation_data->getController()->createDocument($document);
            }

            Billingo_Logger::endDocumentum();
        }

        if (self::should_cancel_document($invoice_generation_data)) {

            Billingo_Logger::startDocumentum();
            $billingo_document = $invoice_generation_data->getRepositroy()
                ->where('type', TypeEnum::INVOICE->value)
                ->where('order_id', $order_id)
                ->first();

            if (!is_null($billingo_document)) {
                $invoice_generation_data->getController()->cancelDocument($billingo_document['billingo_id']);
            }

            Billingo_Logger::endDocumentum();
        }
    }

    public static function should_proforma_generate($order_id): void
    {
        $invoice_generation_data = self::collect_invoice_generation_data($order_id);

        if (get_option('wc_billingo_payment_request_auto') !== 'no') {

            Billingo_Logger::startDocumentum();

            $type = get_option('wc_billingo_payment_request_auto') === TypeEnum::PROFORMA->value
                ? 'getProforma'
                : 'getDraft';

            $document = $invoice_generation_data->getDocumentGenerator()->$type();

            if (!is_null($document)) {
                $invoice_generation_data->getController()->createDocument($document);
            }

            Billingo_Logger::endDocumentum();
        }
    }

    public static function ajax_stornoInvoice(): void
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'billingo'));
        }

        $billingo_repository = new Billingo_Repositroy();
        if(!isset($_POST['order'])) {
            wp_send_json_error(['error' => true, 'messages' => __('A rendelés ID nincs megadva', 'billingo')]);
        } else {
            $order_id = (int)$_POST['order'];
        }

        // Get the order to retrieve the billing email
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['error' => true, 'messages' => __('A rendelés nem található', 'billingo')]);
        }

        $row_in_db = $billingo_repository
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::INVOICE->value)
            ->first();
        $billingo_id = $row_in_db['billingo_id'];
        $response = ['error' => false];
        $client = new BillingoClient(get_option('wc_billingo_api_key'));

        //handles the email sending for the storno because it is not a document genaration, just a cancellation request to the server
        if(in_array(get_option('wc_billingo_storno_email'), ['both', 'billingo'])) {
            $cancellationData = [
                'cancellation_recipients' => $order->get_billing_email()
            ];

            Billingo_Logger::info('Cancelling invoice with email notification to: ' . $order->get_billing_email());

            $billingoResponse = $client->document()->cancelDocument($billingo_id, $cancellationData)->getResponse();
        }else{
            $billingoResponse = $client->document()->cancelDocument($billingo_id)->getResponse();
        }
        if ($billingoResponse->getStatusCode() === Response::HTTP_OK) {
            $data = $billingoResponse->getData();

            $response['messages'][] =
                __('Számla sztornózva: ', 'billingo') . $data->invoice_number;

            $billingo_repository->update($row_in_db['id'], ['canceled_by' => $data->id]);
            $new_db_row = $billingo_repository->createFromDocument($order_id, $data);
            Billingo_Logger::info('Invoice cancel data: ' . json_encode($data->toArray()));

            Billingo_Logger::info('Invoice cancel SUCCESFULL:' . $new_db_row['id']);
        } else {
            Billingo_Logger::error('Invoice cancel FAILED:' . json_encode($billingoResponse->getErrors()));

            $response['error'] = true;
            $response['messages'] = __('A számla sztornózása sikertelen ', 'billingo');
        }

        wp_send_json_success($response);
    }

    public static function ajax_generateInvoice(): void
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'billingo'));
        }
        if(!isset($_POST['order'])) {
            wp_send_json_error(['error' => true, 'messages' => __('A rendelés ID nincs megadva', 'billingo')]);
        } else {
        $orderId = (int)$_POST['order'];
            update_post_meta($orderId, '_is_manual', 'yes');
        }

        Billingo_Logger::info('ajax_generateInvoice raw POST data for wc_billingo_invoice_type: ' . ($_POST['wc_billingo_invoice_type'] ?? 'NOT SET'));
        Billingo_Logger::info('ajax_generateInvoice full relevant POST data: ' . json_encode([
            'order' => $_POST['order'] ?? null,
            'wc_billingo_invoice_type' => $_POST['wc_billingo_invoice_type'] ?? null,
            'wc_billingo_invoice_note' => $_POST['wc_billingo_invoice_note'] ?? null,
            'wc_billingo_invoice_deadline' => $_POST['wc_billingo_invoice_deadline'] ?? null,
            'wc_billingo_invoice_completed' => $_POST['wc_billingo_invoice_completed'] ?? null,
        ]));

        $response['error'] = false;

        $manualIncome = [];
        if (isset($_POST['wc_billingo_invoice_type']) && !empty($_POST['wc_billingo_invoice_type'])) {
            $manualIncome['invoice_type'] = sanitize_text_field($_POST['wc_billingo_invoice_type']);
        }
        if (isset($_POST['wc_billingo_invoice_note'])) {
            $manualIncome['note'] = sanitize_text_field($_POST['wc_billingo_invoice_note']);
        }
        if (isset($_POST['wc_billingo_invoice_deadline']) && !empty($_POST['wc_billingo_invoice_deadline'])) {
            $manualIncome['deadline'] = (int)$_POST['wc_billingo_invoice_deadline'];
        }
        if (isset($_POST['wc_billingo_invoice_completed']) && !empty($_POST['wc_billingo_invoice_completed'])) {
            $manualIncome['completed'] = sanitize_text_field($_POST['wc_billingo_invoice_completed']);
        }

        $invoice = (new Billingo_Document_Generator($orderId, $manualIncome))->get();
        if (is_null($invoice)) {
            $response['error'] = true;
            $response['messages'] = [
                __('Sikertelen generálás', 'billingo'),
                __('A rendelés adatai hiányosak, vagy hibát tartalmaznak', 'billingo'),
            ];
        } else {
            $controller = new Billingo_Controller($orderId);
            $created = $controller->createDocument($invoice);

            if (is_null($created)) {
                $response['error'] = true;
                $response['messages'] = [
                    __('Sikertelen generálás', 'billingo'),
                    __('A rendelés adatai hiányosak, vagy hibát tartalmaznak', 'billingo'),
                ];
            } else {
                $response['messages'] = [
                    __('Sikeres generálás', 'billingo'),
                    __('A számla száma:', 'billingo') . $created->invoice_number,
                ];

                $link = (new Billingo_Repositroy())->where('billingo_id', $created->id)->first()['link'];
                $response['link'] = '<p><a href="'
                    . esc_url($link)
                    . '" id="wc_billingo_download" class="button button-primary" target="_blank">'
                    . __('Számla megtekintése', 'billingo')
                    . '</a></p>';
            }
        }

        wp_send_json_success($response);
    }

    /**
     * Extends order notification e-mail with document link
     *
     * @param WC_Order $order
     * @param boolean $sent_to_admin
     * @param string $plain_text
     * @param boolean $email
     */
    public static function action_woocommerce_email_before_order_table(
        WC_Order $order,
        bool     $sent_to_admin,
        string   $plain_text,
                 $email = false
    ): void
    {
        if (!$email) {

            return;
        }

        $repository = new Billingo_Repositroy();

        $text = '';
        $btn_text = '';
        $pdf_link = false;
        $email_id = $email->id;

        // invoice
        if (in_array(get_option('wc_billingo_email'), ['attach', 'both'])
            && in_array($email_id, [
                'customer_on_hold_order',
                'customer_completed_order',
                'customer_completed_renewal_order',
                'customer_completed_switch_order'
            ])) {
            $pdf_link = $repository
                ->where('order_id', $order->get_id())
                ->where('type', TypeEnum::INVOICE->value)
                ->first();

            $pdf_link = $pdf_link ? $pdf_link['link'] : null;

            $text = get_option(
                'wc_billingo_email_woo_text',
                __('Számlája elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
            $btn_text = get_option('wc_billingo_email_woo_btn', __('Számla megtekintése', 'billingo'));
        }

        // storno
        if (in_array(get_option('wc_billingo_storno_email'), ['attach', 'both'])
            && $email_id == 'customer_refunded_order') {
            $pdf_link = $repository
                ->where('order_id', $order->get_id())
                ->where('type', TypeEnum::CANCELLATION->value)
                ->first();

            $pdf_link = $pdf_link ? $pdf_link['link'] : null;

            $text = get_option('wc_billingo_storno_email_woo_text',
                __('Storno számlája elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
            $btn_text = get_option('wc_billingo_storno_email_woo_btn', __('Storno számla megtekintése','billingo'));
        }

        // proforma
        if (in_array(get_option('wc_billingo_proforma_email'), ['attach', 'both'])
            && in_array($email_id, ['customer_processing_order', 'customer_on_hold_order'])) {
            $pdf_link = $repository
                ->where('order_id', $order->get_id())
                ->where('type', TypeEnum::PROFORMA->value)
                ->first();

            $pdf_link = $pdf_link ? $pdf_link['link'] : null;

            $text = get_option('wc_billingo_proforma_email_woo_text',
                __('Díjbekérője elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
            $btn_text = get_option('wc_billingo_proforma_email_woo_btn', __('Díjbekérő megtekintése', 'billingo'));
        }

        if (!$pdf_link) {
            return;
        }

        echo view('Admin.billingo_invoice_link_button', [
            'text' => $text,
            'btnText' => $btn_text,
            'pdfLink' => $pdf_link,
        ]);
    }

    private static function collect_invoice_generation_data(int $order_id): Invoice_Generation_Container
    {
        return new Invoice_Generation_Container($order_id);
    }

    private static function should_document_generate(Invoice_Generation_Container $invoice_generation_data): bool
    {
        return $invoice_generation_data->getStatus() === $invoice_generation_data->getActivationStatus();
    }

    private static function should_cancel_document(Invoice_Generation_Container $invoice_generation_data): bool
    {
        return $invoice_generation_data->getStatus() === $invoice_generation_data->getAutoStornoStatus();
    }
}
