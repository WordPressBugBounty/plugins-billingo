<?php

namespace App\Billingo\Error;

use App\Billingo\Enums\BillingoErrorEnum;
use JsonSerializable;

class BillingoError implements JsonSerializable
{

    public function __construct(private BillingoErrorEnum $type, private ?array $values)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'values' => $this->values,
        ];
    }

    public function getType(): BillingoErrorEnum
    {
        return $this->type;
    }

    public function setType(BillingoErrorEnum $type): void
    {
        $this->type = $type;
    }

    public function getValues(): ?array
    {
        return $this->values;
    }

    public function setValues(?array $values): void
    {
        $this->values = $values;
    }
}
