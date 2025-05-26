<?php

namespace App\Billingo\Contracts;

use App\Billingo\Api\BillingoApi;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Service\BillingoCollection;
use Closure;

interface BillingoQueryInterface
{
    public function getData(): null|array|BillingoModel|BillingoCollection;

    public function getFilters(): array;

    public function getOwner(): string;
}
