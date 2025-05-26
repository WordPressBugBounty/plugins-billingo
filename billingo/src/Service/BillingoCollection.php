<?php

namespace App\Billingo\Service;

use App\Billingo\Models\BillingoModel;
use Closure;

class BillingoCollection
{
    private array $collection;
    private string $collectionType;

    public function __construct(array $collection)
    {
        $this->collection = $collection;

        if (!empty($collection)) {
            $this->collectionType = get_class($collection[0]);
        }
    }

    public function get(int $index = null): mixed
    {
        return ($index === null || !isset($this->collection[$index - 1]))
            ? $this->collection
            : $this->collection[$index - 1];
    }

    public function first(): mixed
    {
        return $this->get(1);
    }

    public function map(Closure $closure): self
    {
        $modified = [];

        foreach ($this->collection as $item) {
            $modified[] = $closure($item);
        }

        $this->collection = $modified;

        return $this;
    }

    public function each(Closure $closure): self
    {
        foreach ($this->collection as $item) {
            $closure($item);
        }

        return $this;
    }

    public function pluck(string $property): array
    {
        $modified = [];

        foreach ($this->collection as $item) {
            $modified[] = $item->$property;
        }

        return $modified;
    }

    public function append(BillingoModel $billingoModel): bool|self
    {
        if (get_class($billingoModel) !== $this->collectionType) {

            return false;
        }

        $this->collection[] = $billingoModel;

        return $this;
    }

    public function remove(int $index): self
    {
        if (isset($this->collection[$index - 1])) {
            unset($this->collection[$index - 1]);
        }

        return $this;
    }
}
