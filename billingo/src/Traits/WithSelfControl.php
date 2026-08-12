<?php

namespace App\Billingo\Traits;

use App\Billingo\Error\BillingoError;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Service\SelfTest;

trait WithSelfControl
{

    public function hasError(): bool
    {
        $checkNestedArray = function (array $array) use (&$checkNestedArray) {
            foreach ($array as $element) {
                if (is_array($element)) {
                    if ($checkNestedArray($element)) {

                        return true;
                    }
                } else {
                    if ($element instanceof BillingoError) {

                        return true;
                    }
                }
            }

            return false;
        };

        return $checkNestedArray(
            is_array($this->getObjectCheck())
                ? $this->getObjectCheck()
                : [camelToSnake(classBasename($this)) => $this->getObjectCheck()]
        );
    }

    protected function hasBillingoModel(): bool
    {
        foreach ($this->properties as $property) {
            if ($property instanceof BillingoModel) {

                return true;
            }
        }

        return false;
    }

    protected function getObjectCheck(): BillingoError|string|array
    {

        if (!is_null($this->getErrors())) {

            return $this->getErrors();
        }

        if ($this->hasBillingoModel()) {

            $objectsChecks = [];

            foreach ($this->properties as $key => $property) {

                if ($property instanceof BillingoModel) {
                    $objectsChecks[$key] = $property->getObjectCheck();
                }
            }

            return $objectsChecks;
        } else {

            return 'OK';
        }
    }

    public function getSelfTest(): SelfTest
    {
        $controlTree = $this->getObjectCheck();

        return new SelfTest(
            classBasename($this),
            $controlTree instanceof BillingoError
                ? [classBasename($this) => $controlTree]
                : $controlTree
        );
    }
}
