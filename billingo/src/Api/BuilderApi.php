<?php

namespace App\Billingo\Api;

use App\Billingo\Api\BillingoApi;
use App\Billingo\Models\Document\Document;
use App\Billingo\Service\ApiContext;

class BuilderApi extends BillingoApi
{
    protected static string $model;

    public function withApiContext(ApiContext $apiContext): self
    {
        $this->setApiContext($apiContext);

        return $this;
    }

    public function convertTo(string $class): self
    {
        if (class_exists($class)) {

            static::$model = $class;
        }

        return $this;
    }

    public function get(): self
    {
        $this->send();

        return $this;
    }
}
