<?php

namespace App\Billingo\Service;

use App\Billingo\Error\BillingoError;

class SelfTest
{

    private ?array $errors = null;

    public function __construct(private readonly string $name, private readonly array $controlTree)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getControlTree(): array
    {
        return $this->controlTree;
    }

    public function getErrors(): ?array
    {
        if ($this->errors) {

            return $this->errors;
        }

        $this->errors = $this->findErrors($this->controlTree);

        return $this->errors;
    }

    private function findErrors(array $data): ?array
    {
        $errors = [];

        foreach ($data as $class => $status) {

            if (is_array($status)) {
                $localError = $this->findErrors($status);

                if (!empty($localError)) {

                    $errors[$class] = $localError;
                }
            } else {
                if ($status instanceof BillingoError) {
                    $errors[$class] = $status;
                }
            }
        }

        return empty($errors) ? null : $errors;
    }
}
