<?php

namespace App\Billingo\Service;

use App\Billingo\Api\BillingoApi;
use App\Billingo\Contracts\BillingoQueryInterface;
use App\Billingo\Exceptions\BillingoException;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Validator;
use Closure;
use http\Exception\BadMethodCallException;

abstract class BillingoQuery implements BillingoQueryInterface
{
    protected array $filters = [];
    protected array $modified;
    protected string $callableMethod = 'getAll';

    abstract protected function getValidator(): Validator;

    public function getApi(): ?BillingoApi
    {
        return $this->send();
    }

    public function getData(): null|array|BillingoModel|BillingoCollection
    {
        if (isset($this->modified)) {

            return $this->modified;
        }

        $response = $this->send();

        return $response?->getResponse()?->getData();
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function where(string $property, mixed $value): self
    {
        $validator = $this->getValidator();

        try {

            if ($validator->validateProperty($property, $value)) {

                $this->filters[$property] = $value;
            }

        } catch (BillingoException $exception) {
            error_log(sprintf(
                'Billingo query filter "%s" is invalid and was not applied: %s',
                $property,
                $exception->getMessage()
            ));
        }

        return $this;
    }

    public function __call(string $name, array $arguments): self
    {
        if (str_starts_with($name, 'where')) {
            $name = camelToSnake(substr($name, 5));

            if (!array_key_exists($name, $this->getValidator()->getRules())) {

                throw new BadMethodCallException();
            }

            $this->where($name, $arguments[0]);

            return $this;
        }

        throw new BadMethodCallException();
    }

    protected function send(): ?BillingoApi
    {
        if (class_exists($this->getOwner())) {
            $method = $this->callableMethod;

            return (new ($this->getOwner()))->$method($this->getFilters());
        }

        return null;
    }
}
