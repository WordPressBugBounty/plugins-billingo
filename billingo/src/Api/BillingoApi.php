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
    }

    protected function send(string $outputFile = null): self
    {
        $this->response = $this->connector->send($this->apiContext, $outputFile);

        $this->createClassFromResponse();

        return $this;
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

        static::$convertTo = static::DEFAULT_CONVERT_CLASS;
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
