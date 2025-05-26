<?php

namespace App\Billingo\Contracts;

interface SerializableInterface
{
    public function toArray(): array;

    public function fromArray(array $data): self;
}
