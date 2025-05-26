<?php

namespace App\Billingo\Traits;

use App\Billingo\Models\BillingoModel;
use App\Billingo\Service\BillingoCollection;

trait WithFactory
{
    public static function factory($round = 1): null|BillingoModel|BillingoCollection
    {
        if ($round < 1) {
            $round = 1;
        }

        $callerName = static::class;

        $factoryName = "App\Billingo\Factories\\" . classBasename($callerName) . 'Factory';

        if (!class_exists($factoryName)) {

            return null;
        }

        $fakers = [];

        for ($i = 0; $i < $round; $i++):
            $fakers[] = new $callerName((new $factoryName)->definition());
        endfor;

        $fakers = billingoCollection($fakers);

        return $round === 1 ? $fakers->first() : $fakers;
    }
}
