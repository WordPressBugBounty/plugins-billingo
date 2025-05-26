<?php

use App\Billingo\Service\BillingoCollection;
use App\Billingo\Service\BillingoTranslator;
use App\Billingo\WooCommerce\Service\Billingo_View;
use Symfony\Component\VarDumper\VarDumper;

if (!function_exists('getEnumValues')) {
    function getEnumValues(string $enum): array
    {
        return array_map(fn($enum) => $enum->value, $enum::cases());
    }
}

if (!function_exists('get_wp_translated_enum')) {
    function get_wp_translated_enum(string $enum, bool $wp_translate = false): array
    {
        $translated = [];

        foreach ($enum::cases() as $enum) {
            $translated[$enum->value] = $wp_translate
                ? __($enum->getReadableText(), 'billingo')
                : $enum->getReadableText();
        }

        return $translated;
    }
}

if (!function_exists('classBasename')) {
    function classBasename($class): string
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            VarDumper::dump($vars[0]);
        } else {
            foreach ($vars as $k => $v) {
                VarDumper::dump($v, is_int($k) ? 1 + $k : $k);
            }
        }

        exit(1);
    }
}

if (!function_exists('camelToSnake')) {
    function camelToSnake(string $input): string
    {
        $snake = preg_replace('/[A-Z]/', '_$0', $input);

        $snake = strtolower($snake);

        if (str_starts_with($snake, '_')) {
            $snake = substr($snake, 1);
        }

        return $snake;
    }
}

if (!function_exists('trans')) {
    function trans(string $key): string
    {
        $translator = BillingoTranslator::getInstance();

        return $translator->translate($key);
    }
}

if (!function_exists('view')) {
    function view(string $key, array $content = []): string
    {
        $view = Billingo_View::getInstance();

        return $view->getViewContent($key, $content);
    }
}

if (!function_exists('isArraySubset')) {
    function isArraySubset(array $subset, array $set): bool
    {
        foreach ($subset as $key => $value) {
            if (is_array($value)) {
                if (!isset($set[$key]) || !is_array($set[$key]) || !isArraySubset($value, $set[$key])) {

                    return false;
                }
            } else {
                if (!array_key_exists($key, $set) || $set[$key] != $value) {

                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('billingoCollection')) {
    function billingoCollection(array $collection): BillingoCollection
    {
        return new BillingoCollection($collection);
    }
}

if (!function_exists('wcIsTrue')) { //TODO: visszamenőleg javítani
    function wcIsTrue(string $value): bool
    {
        return $value === 'yes';
    }
}

