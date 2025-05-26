<?php

namespace App\Billingo\Validation;

use Valitron\Validator as Valitron;

class RuleConfigurator
{
    private static ?self $instance = null;

    private function __construct()
    {
        $this->initRules();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function initRules(): void
    {
        Valitron::addRule('string', function ($field, $value, array $params, array $fields) {

            return is_string($value);
        }, 'must be a string');

        Valitron::addRule('instanceOfAny', function ($field, $value, array $params, array $fields) {
            if (!is_object($value)) {

                return false;
            }

            foreach ($params[0] as $class) {
                if ($value instanceof $class) {

                    return true;
                }
            }

            return false;
        }, 'must be an instance of one of the specified classes');

        Valitron::addRule('regex', function ($field, $value, array $params, array $fields) {

            if (is_null($value)) {

                return false;
            }

            return preg_match($params[0], $value);
        }, 'contains invalid characters');

        Valitron::addRule('dateFormat', function ($field, $value, array $params, array $fields) {

            if (is_null($value)) {

                return false;
            }

            $parsed = date_parse_from_format($params[0], $value);

            return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
        });
    }
}
