<?php

namespace App\Billingo\Service;

use App\Billingo\Models\BillingoModel;

class BillingoResponse
{

    public function __construct(
        private readonly string                        $reasonPhrase,
        private readonly int                           $statusCode,
        private readonly array                         $meta = [],
        private array|BillingoCollection|BillingoModel $data = [],
        private readonly array                         $errors = [],
    )
    {
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): array|BillingoCollection|BillingoModel
    {
        return $this->data;
    }

    public function setData(array|BillingoCollection|BillingoModel $data): void
    {
        $this->data = $data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
