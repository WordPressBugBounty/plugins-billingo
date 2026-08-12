<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\DocumentBlockQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\DocumentBlock\DocumentBlock;
use App\Billingo\Models\DocumentBlock\DocumentBlockSchema;

class DocumentBlockApi extends BillingoApi
{
    const PREFIX = 'document-blocks';
    const DEFAULT_CONVERT_CLASS = DocumentBlock::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    public function query(): DocumentBlockQuery
    {
        return new DocumentBlockQuery();
    }

    /**
     * Retrieves public url to download an existing document.
     * @param  array  $content
     * @return DocumentBlockApi
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
     * Create a document block. Returns a document block object if the creation is succeeded.
     * @param  array|DocumentBlockSchema  $documentBlockSchema
     * @return DocumentBlockApi
     * @throws BadContentException
     */
    public function create(array|DocumentBlockSchema $documentBlockSchema): self
    {
        if (is_array($documentBlockSchema)) {
            $documentBlockSchema = new DocumentBlockSchema($documentBlockSchema);
        }

        if ($documentBlockSchema->hasError()) {

            throw new BadContentException($documentBlockSchema->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($documentBlockSchema->toArray());

        return $this->send();
    }
}
