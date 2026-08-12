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
    private ?string $lastError = null;

    public function __construct(private readonly int $orderId)
    {
        $this->client = new BillingoClient(get_option('wc_billingo_api_key', ''));
        $this->blockProforma = get_option('wc_billingo_disable_proforma_invoicing') === 'yes';
        $this->repository = new Billingo_Repositroy();
    }

    /**
     * A generálás sikertelensége esetén a tényleges okot adja vissza (ember által
     * olvasható formában), hogy a manuális generálás felülete ne csak egy általános
     * "sikertelen generálás" üzenetet mutasson.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
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
            $this->lastError = sprintf(
                __('Már van érvényes számla ehhez a rendeléshez (%s), előbb sztornózni kell, mielőtt újat lehetne kiállítani.', 'billingo'),
                $hasInvoice['billingo_number']
            );
            Billingo_Logger::info($errorMessage);

            return null;
        }else {

            $hasProforma = $this->repository
            ->where('type', TypeEnum::PROFORMA->value)
            ->where('order_id', $this->orderId)
            ->first();

            $canUseProforma = !$this->blockProforma && 
            $hasProforma !== null && 
            isset($hasProforma['billingo_id']) && 
            !empty($hasProforma['billingo_id']);

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

            $detailedMessage = $this->flattenErrorMessage($exception->getSelfTest()->getErrors() ?? []);
            $this->lastError = $detailedMessage !== ''
                ? $detailedMessage
                : __('A rendelés adatai érvénytelenek a Billingo API szerint.', 'billingo');
            Billingo_Logger::error('Billingo server creation FAILED: ' . $exception->getMessage() . ' | Részletes hiba: ' . $this->lastError);

            return null;
        }

        if ($response->getStatusCode() === Response::HTTP_CREATED) {

            $created = $response->getData();
            Billingo_Logger::info("Billingo server successfully created, ID: = {$created->id}");

            $this->store($created);

            return $created;

        } else {

            $apiErrors = $response->getErrors();
            $stringifiedErrors = $this->stringifyApiErrors($apiErrors);
            $this->lastError = $stringifiedErrors !== ''
                ? $stringifiedErrors
                : sprintf(__('A Billingo API hibával válaszolt (HTTP %d).', 'billingo'), $response->getStatusCode());
            Billingo_Logger::error('Billingo server creation FAILED: '
                . json_encode($apiErrors));

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
            $apiErrors = $response->getErrors();
            $stringifiedErrors = $this->stringifyApiErrors($apiErrors);
            $this->lastError = $stringifiedErrors !== ''
                ? $stringifiedErrors
                : sprintf(__('A díjbekérőből történő számlázás sikertelen (HTTP %d).', 'billingo'), $response->getStatusCode());
            Billingo_Logger::error('Billingo server creation from proforma: FAILED '
                . json_encode($apiErrors));

            return null;
        }
    }

    /**
     * A getSelfTest()->getErrors() beágyazott, tetszőleges mélységű tömböt ad vissza,
     * aminek levelei BillingoError objektumok — ez egy rövid, ember által olvasható
     * szöveggé alakítja.
     */
    private function flattenErrorMessage(array $errors, string $prefix = ''): string
    {
        $messages = [];

        foreach ($errors as $key => $value) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;

            if ($value instanceof \App\Billingo\Error\BillingoError) {
                $fieldMessages = $value->getValues();

                if (!empty($fieldMessages)) {
                    foreach ($fieldMessages as $field => $fieldErrors) {
                        $messages[] = $path . '.' . $field . ': ' . implode(', ', (array)$fieldErrors);
                    }
                } else {
                    $messages[] = $path . ': ' . $value->getType()->value;
                }
            } elseif (is_array($value)) {
                $nested = $this->flattenErrorMessage($value, $path);

                if ($nested !== '') {
                    $messages[] = $nested;
                }
            }
        }

        return implode(' | ', $messages);
    }

    /**
     * A Billingo API hibaválasza (BillingoResponse::getErrors()) tetszőleges, esetenként
     * beágyazott tömb lehet (mezőnév => hibaüzenet-lista). Ez összegyűjti a tényleges
     * szöveges hibaüzeneteket (a nyers "error.message: ..." elérési út nélkül, ami a
     * felhasználó számára értelmezhetetlen), és ismert hibatípusoknál közérthető, magyar
     * magyarázatra cseréli.
     */
    private function stringifyApiErrors(array $errors): string
    {
        $rawMessages = $this->collectApiErrorMessages($errors);

        if (empty($rawMessages)) {
            return '';
        }

        $friendlyMessages = array_unique(array_map(
            fn($message) => $this->translateApiErrorMessage($message),
            $rawMessages
        ));

        return implode(' | ', $friendlyMessages);
    }

    private function collectApiErrorMessages(array $errors): array
    {
        $messages = [];

        foreach ($errors as $value) {
            if (is_array($value)) {
                $messages = array_merge($messages, $this->collectApiErrorMessages($value));
            } elseif (is_string($value) && $value !== '') {
                $messages[] = $value;
            }
        }

        return $messages;
    }

    /**
     * Ismert, gyakran előforduló Billingo API hibaüzeneteket fordít le közérthető,
     * magyar, a boltos számára ténylegesen hasznos magyarázatra. Ismeretlen üzenetnél a
     * Billingo eredeti szövegét adja vissza változatlanul.
     */
    private function translateApiErrorMessage(string $message): string
    {
        if (stripos($message, 'duplicate vendor id') !== false) {
            return __(
                'A Billingo szerint ehhez a rendeléshez korábban már beérkezett egy azonos bizonylat-kérés '
                . '(duplikátum-védelem). Ez általában akkor fordul elő, ha egy korábbi generálási kísérlet '
                . 'ténylegesen létrehozta a bizonylatot a Billingo oldalán, de a helyi nyilvántartásba '
                . 'valamiért nem került be. Ellenőrizd a Billingo felületén, hogy létrejött-e már a bizonylat '
                . 'ehhez a rendeléshez — ha igen, nincs teendő; ha nem, próbáld újra néhány perc múlva.',
                'billingo'
            );
        }

        return $message;
    }

    private function store(Document $document): void
    {
        $this->repository->createFromDocument($this->orderId, $document);
    }
}
