<?php

namespace App\Billingo\Contracts;

use App\Billingo\Service\SelfTest;

interface SelfControlInterface
{
    public function hasError(): bool;

    public function getSelfTest(): SelfTest;
}
