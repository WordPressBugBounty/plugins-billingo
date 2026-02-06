<?php

namespace App\Billingo\WooCommerce\Controllers;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Document\Document;
use App\Billingo\Models\Document\DocumentInsert;
use App\Billingo\Service\BillingoClient;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use Symfony\Component\HttpFoundation\Response;

class Billingo_Controller
{

    private readonly BillingoClient $client;
    private readonly Billingo_Repositroy $repository;
    private bool $blockProforma;

    public function __construct(private readonly int $orderId)
    {
        $this->client = new BillingoClient(get_option('wc_billingo_api_key', ''));
        $this->blockProforma = get_option('wc_billingo_disable_proforma_invoicing') === 'yes';
        $this->repository = new Billingo_Repositroy();
    }

    public function createDocument(DocumentInsert $document): ?Document
    {

        Billingo_Logger::info('Document type of creating: ' . $document->type);
        $hasInvoice = $this->repository
            ->where('type', TypeEnum::INVOICE->value)
            ->where('order_id', $this->orderId)
            ->where('canceled_by', null)
            ->first();

        if ($hasInvoice && $document->type === TypeEnum::INVOICE->value) {
            $errorMessage ='Már van érvényes számla a ' . $this->orderId . 'számú  rendelésre, nem kell újat létrehozni, amennyiben mégis újat szeretne létrehozni, előbb sztornózza a már meglévő számlát! : Számla neve: ' . $hasInvoice['billingo_number'];
            Billingo_Logger::info($errorMessage);
            
            return null;
        }else {

            $hasProforma = $this->repository
            ->where('type', TypeEnum::PROFORMA->value)
            ->where('order_id', $this->orderId)
            ->get();

            $canUseProforma = !$this->blockProforma && 
            $hasProforma !== null && 
            isset($hasProforma['billingo_id']) && 
            !empty($hasProforma['billingo_id']);

        $this->shouldDisableWcEmail($document->type);

            if ($canUseProforma) {
                return $this->createInvoiceFromProforma($hasProforma['billingo_id']);
            }
            else if ($document->type === TypeEnum::DRAFT->value) {
                Billingo_Logger::info('Piszkozat létrehozására beérkező igény');
                return $this->createInvoice($document);
            }
            else {
                return $this->createInvoice($document);
            }
        }

    }

    public function cancelDocument(int $billingoId, array $body = []): bool
    {
        //handles the email sending for the storno because it is not a document genaration, just a cancellation request to the server
        //todo: refactor: Standard_Init.php ajax_stornoInvoice() also use the same logic, move to 
        if(in_array(get_option('wc_billingo_storno_email'), ['both', 'billingo'])) {
        if (empty($body)) {
            $order = wc_get_order($this->orderId);
            if ($order && $order->get_billing_email()) {
                $body = [
                    'cancellation_recipients' => $order->get_billing_email()
                ];
                Billingo_Logger::info('Auto-setting cancellation email recipient for order ' . $this->orderId . ': ' . $order->get_billing_email());
            }
        }

            $response = $this->client->document()->cancelDocument($billingoId, $body)->getResponse();
        }else{
            $response = $this->client->document()->cancelDocument($billingoId)->getResponse();
        }
        if ($response->getStatusCode() === Response::HTTP_OK) {

            $canceledDocument = $response->getData();
            Billingo_Logger::info("Invoice cancel SUCCESSFUL ID: {$canceledDocument->id}");
            $databaseRow = $this->repository->where('billingo_id', $billingoId)->first();

            if (!is_null($databaseRow)) {
                $this->repository->update($databaseRow['id'], ['canceled_by' => $canceledDocument->id]);
            }

            $this->repository->createFromDocument($this->orderId, $canceledDocument);

            return true;
        } else {
            $errors = $response->getErrors();
            Billingo_Logger::error('Invoice cancel FAILED: ' . json_encode($errors));

            return false;
        }
    }

    public function getLink(int $id): ?string
    {
        $response = $this->client
            ->document()
            ->getPublicUrlForDocument($id)
            ->getResponse();

        if ($response->getStatusCode() == Response::HTTP_OK) {

            return $response->getData()['public_url'];
        }
        Billingo_Logger::warning("Cant create a link for ID: {$id}");

        return null;
    }

    private function createInvoice(DocumentInsert $document): ?Document
    {
        try {
            $response = $this->client
                ->document()
                ->create($document)
                ->getResponse();
        } catch (BadContentException $exception) {

            Billingo_Logger::error('Billingo server creation FAILED: ' . $exception->getMessage());

            return null;
        }

        if ($response->getStatusCode() === Response::HTTP_CREATED) {

            $created = $response->getData();
            Billingo_Logger::info("Billingo server successfully created, ID: = {$created->id}");

            $this->store($created);

            return $created;

        } else {

            Billingo_Logger::error('Billingo server creation FAILED: '
                . json_encode($response->getErrors()));

            return null;
        }
    }

    private function createInvoiceFromProforma(int $id): ?Document
    {
        $response = $this->client
            ->document()
            ->createFromProforma($id)
            ->getResponse();

        if ($response->getStatusCode() === Response::HTTP_CREATED) {

            $proforma = $response->getData();

            Billingo_Logger::info("Billingo server successfully created from proforma, ID: = {$proforma->id}");

            $this->store($proforma);

            return $response->getData();
        } else {
            Billingo_Logger::error('Billingo server creation from proforma: FAILED '
                . json_encode($response->getErrors()));

            return null;
        }
    }

    private function store(Document $document): void
    {
        $this->repository->createFromDocument($this->orderId, $document);
    }

    private function shouldDisableWcEmail(string $type): void
    {
        $wcEmailId = match($type){
            'invoice' => 'wc_billingo_email',
            'proforma' => 'wc_billingo_proforma_email',
            'cancellation' => 'wc_billingo_storno_email',
            default => null,
        };

        $enabled = in_array(get_option($wcEmailId), ['attach', 'both'])
            ? 'yes'
            : 'no';

        if ($enabled === 'yes') {
            Billingo_Logger::info('Email send by WooCommerce');
            $this->wcEmailToggle(true);
        } else {
            $this->wcEmailToggle(false);
        }
    }

    private function wcEmailToggle(bool $on): void
    {
        $turnOffCallback = fn() => $on;

        add_filter('woocommerce_email_enabled_customer_completed_order', $turnOffCallback, 10, 2);
        add_filter('woocommerce_email_enabled_customer_refunded_order', $turnOffCallback, 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', $turnOffCallback, 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order', $turnOffCallback, 10, 2);
    }
}
