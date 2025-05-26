<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\SelfControlInterface;
use App\Billingo\Contracts\SerializableInterface;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Enums\BillingoErrorEnum;
use App\Billingo\Error\BillingoError;
use App\Billingo\Exceptions\EmptyObjectException;
use App\Billingo\Exceptions\ValidationException;
use App\Billingo\Traits\WithSelfControl;

abstract class BillingoModel implements SerializableInterface, SelfControlInterface
{
    use WithSelfControl;

    protected ValidableInterface $validator;
    protected array $cast = [];
    protected array $properties;

    public function __construct(array $data = [], ?ValidableInterface $validator = null)
    {
        $this->validator = $validator ?? $this->getValidator();

        $this->fromArray($data);
    }

    abstract protected function getValidator(): ValidableInterface;

    public function toArray(): array
    {
        $toArray = $this->properties;

        foreach ($toArray as $key => $value) {
            $toArray[$key] = $this->serializeValue($value);
        }

        return $toArray;
    }

    public function fromArray(array $data): self
    {
        if (empty($data)) {
            $this->properties = [];
        }

        $withClasses = $this->createClasses($data, $this->cast);

        $validated = [];

        try {

            $validated = $this->validator->validate($withClasses);
        } catch (EmptyObjectException $exception) {
            $this->validator->setErrors(BillingoErrorEnum::EMPTY_OBJECT);
        } catch (ValidationException $exception) {
            $this->validator->setErrors(BillingoErrorEnum::VALIDATION_FAILED, $exception->getErrors());
        }

        $this->properties = $validated;

        return $this;
    }

    private function serializeValue($value): mixed
    {
        if (is_object($value)) {
            return $value instanceof SerializableInterface ? $value->toArray() : $value;
        } elseif (is_array($value)) {
            return array_map(
                fn($item) => $item instanceof SerializableInterface ? $item->toArray() : $item,
                $value
            );
        }
        return $value;
    }

    protected function createClasses(array $data, array $castingList): array
    {
        $convertedData = [];

        foreach ($data as $key => $value) {
            $convertedData[$key] = $this->convertValue($key, $value, $castingList);
        }

        return $convertedData;
    }

    private function convertValue(string $key, mixed $value, array $castingList): mixed
    {
        if (is_object($value)) {

            return $value;
        } elseif (isset($castingList[$key])) {
            if (is_array($castingList[$key])) {

                return array_map(
                    fn($item) => is_object($item) ? $item : new $castingList[$key][0]($item),
                    $value
                );
            } else {

                return $value ? new $castingList[$key]($value) : null;
            }
        }

        return $value;
    }

    public function getErrors(): ?BillingoError
    {
        return $this->validator->getErrors();
    }

    public function __get(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }


    public function __set(string $name, mixed $value): void
    {
        if (!$this->validator->validateProperty($name, $value)) {

            return;
        }

        if (isset($this->properties[$name])) {

            $this->properties[$name] = $value;
        }
    }
}
