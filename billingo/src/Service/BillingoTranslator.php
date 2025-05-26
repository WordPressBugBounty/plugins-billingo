<?php

namespace App\Billingo\Service;

class BillingoTranslator
{
    protected $translator = [];
    private static ?BillingoTranslator $instance = null;

    private function __construct(?string $locale)
    {
        if (is_null($locale)) {
            $config = require __DIR__ . '/../config.php';
            $locale = $config['local'];
        }

        $this->loadTranslations($locale);
    }

    private function loadTranslations(string $locale): void
    {
        $langPath = __DIR__ . '/../Lang/' . $locale;
        
        if (!is_dir($langPath)) {
            return;
        }
        
        $files = glob($langPath . '/*.php');
        
        foreach ($files as $file) {
            $group = basename($file, '.php');
            $this->translator[$group] = require $file;
        }
    }

    public static function getInstance(?string $locale = null): BillingoTranslator
    {
        if (self::$instance == null) {
            self::$instance = new BillingoTranslator($locale);
        }

        return self::$instance;
    }

    public function translate($key): string
    {
        $segments = explode('.', $key);
        
        if (count($segments) < 2) {
            return $key;
        }
        
        $group = $segments[0];
        $item = $segments[1];
        
        if (!isset($this->translator[$group]) || !isset($this->translator[$group][$item])) {
            return $key;
        }
        
        return $this->translator[$group][$item];
    }
}
