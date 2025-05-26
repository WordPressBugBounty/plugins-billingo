<?php

namespace App\Billingo\Contracts;

interface ValidableInterface
{
    public function validate(array $data): array;

    public function validateProperty(string $key, mixed $value): bool;
}
