<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\DocumentQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Document\Document;
use App\Billingo\Models\Document\DocumentCancellation;
use App\Billingo\Models\Document\DocumentInsert;
use App\Billingo\Models\Document\InvoiceSettings;
use App\Billingo\Models\Document\ModificationDocumentInsert;
use App\Billingo\Models\Document\OnlineSzamlaStatus;
use App\Billingo\Models\Document\ReceiptInsert;
use App\Billingo\Models\PaymentHistory;
use App\Billingo\Models\SendDocument;

class DocumentApi extends BillingoApi
{
    const PREFIX = 'documents';
    const DEFAULT_CONVERT_CLASS = Document::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * This method creates and returns a new instance of the DocumentQuery class,
     * which can be used to query documents.
     * @return DocumentQuery
     */
    public function query(): DocumentQuery
    {
        return new DocumentQuery();
    }

    /**
     * Returns a list of your documents. The documents are returned sorted by creation date,
     * with the most recent documents appearing first.
     * @param array $content
     * @return self
     */
    public function getAll(array $content = []): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX)
            ->setContent($content);

        return $this->send();
    }

    /**
     * Create a new document. Returns a document object if the creation is succeeded.
     * @param array|DocumentInsert $document
     * @return DocumentApi
     * @throws BadContentException
     */
    public function create(array|DocumentInsert $document): self
    {
        if (is_array($document)) {
            $document = new DocumentInsert($document);
        }

        if ($document->hasError()) {

            throw new BadContentException($document->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($document->toArray());

        return $this->send();
    }

    /**
     * Create a new receipt. Returns a document object if the creation is succeeded.
     * @param array|ReceiptInsert $receipt
     * @return DocumentApi
     * @throws BadContentException
     */
    public function createReceipt(array|ReceiptInsert $receipt): self
    {
        if (is_array($receipt)) {
            $receipt = new ReceiptInsert($receipt);
        }

        if ($receipt->hasError()) {

            throw new BadContentException($receipt->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX . '/receipt')
            ->setContent($receipt->toArray());

        return $this->send();
    }

    /**
     * Converts a draft to a receipt. Returns the receipt object if the convert is succeeded.
     * @param int $id
     * @param array|ReceiptInsert $receipt
     * @return DocumentApi
     * @throws BadContentException
     */
    public function convertDraftToReceipt(int $id, array|ReceiptInsert $receipt): self
    {
        if (is_array($receipt)) {
            $receipt = new ReceiptInsert($receipt);
        }

        if ($receipt->hasError()) {

            throw new BadContentException($receipt->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX . "/receipt/{$id}")
            ->setContent($receipt->toArray());

        return $this->send();
    }

    /**
     * Retrieves the details of an existing document by vendor id.
     * @param int $id
     * @return DocumentApi
     */
    public function getByVendorId(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/vendor/{$id}");

        return $this->send();

    }

    /**
     * Retrieves the details of an existing document.
     * @param int $id
     * @return self
     */
    public function getById(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}");

        return $this->send();
    }

    /**
     * Converts a draft to an invoice. Returns the invoice object if the convert is succeeded.
     * @param int $id
     * @param array|DocumentInsert $document
     * @return DocumentApi
     * @throws BadContentException
     */
    public function convertDraftToInvoice(int $id, array|DocumentInsert $document): self
    {
        if (is_array($document)) {
            $document = new DocumentInsert($document);
        }

        if ($document->hasError()) {

            throw new BadContentException($document->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX . "/{$id}")
            ->setContent($document->toArray());

        return $this->send();
    }

    /**
     * Delete an existing draft.
     * @param int $id
     * @return DocumentApi
     */
    public function deleteDraft(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::DELETE)
            ->setUrl(self::PREFIX . "/{$id}");

        return $this->send();
    }

    /**
     * Archive an existing proforma document.
     * @param int $id
     * @return DocumentApi
     */
    public function archiveProformaDocument(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX . "/{$id}/archive");

        return $this->send();
    }

    /**
     * Cancel a document. Returns a cancellation document object if the cancellation is succeeded.
     * @param int $id
     * @param DocumentCancellation|array $documentCancellation
     * @return DocumentApi
     */
    public function cancelDocument(int $id, DocumentCancellation|array $documentCancellation = []): self
    {
        if (is_array($documentCancellation) && !empty($documentCancellation)) {
            $documentCancellation = new DocumentCancellation($documentCancellation);
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX . "/{$id}/cancel")
            ->setContent(empty($documentCancellation)
                ? $documentCancellation
                : $documentCancellation->toArray()
            );

        return $this->send();
    }

    /**
     * Copy a document. Returns the new document if the copy was succeeded.
     * @param int $id
     * @return DocumentApi
     */
    public function copyDocument(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX . "/{$id}/copy");

        return $this->send();
    }

    /**
     * Create a new document from proforma. Returns a document object if the creation is succeeded.
     * @param int $id
     * @param InvoiceSettings|array $invoiceSetting
     * @return DocumentApi
     */
    public function createFromProforma(int $id, InvoiceSettings|array $invoiceSetting = []): self
    {
        if (is_array($invoiceSetting) && !empty($invoiceSetting)) {
            $invoiceSetting = new InvoiceSettings($invoiceSetting);
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX . "/{$id}/create-from-proforma")
            ->setContent(empty($invoiceSetting)
                ? $invoiceSetting
                : $invoiceSetting->toArray()
            );

        return $this->send();
    }

    /**
     * Create a modification document for the given document.
     * Returns a new document object if the creation is successful.
     * @param int $id
     * @param ModificationDocumentInsert|array $modificationDocument
     * @return DocumentApi
     * @throws BadContentException
     */
    public function createModificationDocument(int $id, ModificationDocumentInsert|array $modificationDocument): self
    {
        if (is_array($modificationDocument)) {
            $modificationDocument = (new ModificationDocumentInsert($modificationDocument));
        }

        if ($modificationDocument->hasError()) {

            throw new BadContentException($modificationDocument->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX . "/{$id}/create-modification-document")
            ->setContent($modificationDocument->toArray());

        return $this->send();
    }

    /**
     * Download a document. Returns a document in PDF format.
     * @param int $id
     * @param string|null $outputFile
     * @return DocumentApi
     */
    public function downloadDocumentInPdf(int $id, string $outputFile = null): self
    {
        self::$convertTo = '';

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/download");

        $result = $this->send($outputFile);
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }

    /**
     * Retrieves the details of an existing document status.
     * @param int $id
     * @return DocumentApi
     */
    public function getOnlineDocument(int $id): self
    {
        self::$convertTo = OnlineSzamlaStatus::class;

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/online-szamla");

        $result = $this->send();
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }

    /**
     * Retrieves the details of payment history an existing document.
     * @param int $id
     * @return DocumentApi
     */
    public function getPaymentHistory(int $id): self
    {
        self::$convertTo = PaymentHistory::class;

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/payments");

        $result = $this->send();
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }

    /**
     * Update payment history an existing document.
     * Returns a payment history object if the update is succeeded.
     * @param int $id
     * @param PaymentHistory|array $paymentHistory
     * @return DocumentApi
     * @throws BadContentException
     */
    public function updatePaymentHistory(int $id, PaymentHistory|array $paymentHistory): self
    {
        self::$convertTo = PaymentHistory::class;

        if (is_array($paymentHistory)) {
            $paymentHistory = (new PaymentHistory($paymentHistory));
        }

        if ($paymentHistory->hasError()) {

            throw new BadContentException($paymentHistory->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX . "/{$id}/payments")
            ->setContent($paymentHistory->toArray());

        $result = $this->send();
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }

    /**
     * Delete all exist payment history on document.
     * @param int $id
     * @return DocumentApi
     */
    public function deletePaymentHistory(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::DELETE)
            ->setUrl(self::PREFIX . "/{$id}/payments");

        return $this->send();
    }

    /**
     * Returns a printable POS PDF file of a particular document.
     * @param int $id
     * @param string|null $fileOutput
     * @param bool $isSize58
     * @return DocumentApi
     */
    public function downloadPosPdf(int $id, string $fileOutput = null, bool $isSize58 = true): self
    {
        self::$convertTo = '';

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/print/pos")
            ->setContent(['size' => $isSize58 ? 58 : 80]);

        $result = $this->send($fileOutput);
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }

    /**
     * Retrieves public url to download an existing document.
     * @param int $id
     * @return DocumentApi
     */
    public function getPublicUrlForDocument(int $id): self
    {
        self::$convertTo = '';

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/public-url");

        $result = $this->send();
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }

    /**
     * Returns a list of emails, where the invoice is sent.
     * @param int $id
     * @param SendDocument|array $sendDocument
     * @return DocumentApi
     * @throws BadContentException
     */
    public function sendDocumentToEmail(int $id, SendDocument|array $sendDocument): self
    {
        self::$convertTo = SendDocument::class;

        if (is_array($sendDocument)) {
            $sendDocument = (new SendDocument($sendDocument));
        }

        if ($sendDocument->hasError()) {

            throw new BadContentException($sendDocument->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX . "/{$id}/send")
            ->setContent($sendDocument->toArray());

        $result = $this->send();
        self::$convertTo = self::DEFAULT_CONVERT_CLASS;

        return $result;
    }
}
