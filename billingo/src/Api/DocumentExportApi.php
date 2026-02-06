<?php

namespace App\Billingo\Api;

use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\DocumentExport\CreateDocumentExport;
use App\Billingo\Models\DocumentExport\DocumentExportId;
use App\Billingo\Models\DocumentExport\DocumentExportStatusState;

class DocumentExportApi extends BillingoApi
{
    const PREFIX = 'document-export';
    const DEFAULT_CONVERT_CLASS = '';

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * Return with the id of the export.
     * @param  array|CreateDocumentExport  $createDocumentExport
     * @return DocumentExportApi
     * @throws BadContentException
     */
    public function create(array|CreateDocumentExport $createDocumentExport): self
    {

        self::$convertTo = DocumentExportId::class;

        if (is_array($createDocumentExport)) {
            $createDocumentExport = new CreateDocumentExport($createDocumentExport);
        }

        if ($createDocumentExport->hasError()) {

            throw new BadContentException(esc_html( $createDocumentExport->getSelfTest()));
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($createDocumentExport->toArray());

        return $this->send();
    }

    /**
     * Return the exported file.
     * @param  int  $id
     * @param  string|null  $outputFile
     * @return DocumentExportApi
     */
    public function download(int $id, string $outputFile = null): self
    {

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/download");

        return $this->send($outputFile);
    }

    /**
     * Return state of the given export.
     * @param  int  $id
     * @return DocumentExportApi
     */
    public function getExportState(int $id): self
    {
        self::$convertTo = DocumentExportStatusState::class;

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}/poll");

        return $this->send();
    }

}
