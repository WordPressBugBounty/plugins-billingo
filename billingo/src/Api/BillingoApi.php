<?php

namespace App\Billingo\Api;

use App\Billingo\Models\BillingoModel;
use App\Billingo\Service\ApiContext;
use App\Billingo\Service\BillingoCollection;
use App\Billingo\Service\BillingoConnector;
use App\Billingo\Service\BillingoResponse;

class BillingoApi
{
    protected ApiContext $apiContext;
    protected BillingoConnector $connector;
    protected BillingoResponse $response;

    public function __construct()
    {
        $this->apiContext = new ApiContext();
        $this->connector = BillingoConnector::getInstance();

        // A $convertTo statikus (osztályonként közös), ezért egy korábbi, a send() előtt megszakadt hívás
        // (pl. validációs kivétel) az értékét itt felejthette volna: új példány mindig tiszta lappal indul.
        $this->resetConvertTo();
    }

    protected function send(?string $outputFile = null): self
    {
        try {
            $this->response = $this->connector->send($this->apiContext, $outputFile);

            $this->createClassFromResponse();
        } finally {
            // Akkor is vissza kell állítani, ha a küldés kivételt dob, vagy a válasz üres/hibás volt
            // (a konvertálás korai visszatérése): különben a következő hívás válasza rossz osztályba
            // (pl. üres SendDocument) konvertálódna.
            $this->resetConvertTo();
        }

        return $this;
    }

    private function resetConvertTo(): void
    {
        if (property_exists(static::class, 'convertTo') && defined(static::class . '::DEFAULT_CONVERT_CLASS')) {
            static::$convertTo = static::DEFAULT_CONVERT_CLASS;
        }
    }

    private function createClassFromResponse(): void
    {
        if (!isset(static::$convertTo) ||
            !class_exists(static::$convertTo) ||
            empty($this->response->getData())
        ) {
            return;
        }

        $converted = $this->convertToObject($this->response->getData());

        $this->response->setData($converted);
    }

    protected function convertToObject(array|BillingoCollection $data): BillingoCollection|BillingoModel
    {
        $isAssociativeArray = is_array($data) && array_keys($data) !== range(0, count($data) - 1);

        if ($isAssociativeArray) {
            return new static::$convertTo($data);
        } elseif (is_array($data)) {
            return billingoCollection(array_map(
                fn($shouldModel) => new static::$convertTo($shouldModel),
                $data
            ));
        }

        return $data;
    }

    public function getResponse(): BillingoResponse
    {
        return $this->response;
    }

    public function getApiContext(): ApiContext
    {
        return $this->apiContext;
    }

    public function setApiContext(ApiContext $apiContext): self
    {
        $this->apiContext = $apiContext;

        return $this;
    }
}
