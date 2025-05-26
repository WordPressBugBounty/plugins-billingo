<?php

namespace App\Billingo\Factories;

use App\Billingo\Contracts\FactoryInterface;
use Faker\Factory;
use Faker\Generator;

abstract class BaseFactory implements FactoryInterface
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }
}
