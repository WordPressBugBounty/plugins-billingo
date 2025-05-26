<?php

namespace App\Billingo\WooCommerce\Service;

class Billingo_Logger
{
    private string $fileName;
    private string $date;

    private function __construct(private readonly string $logMessage)
    {
        $logdir = BILLINGO__PLUGIN_DIR . '/log';

        if (!is_dir($logdir)) {
            mkdir($logdir, 0777, true);
        }

        $this->date = gmdate('Y-m-d');
        $this->fileName = "{$logdir}/{$this->date}.txt";
    }

    public static function info(string $message): void
    {
        $timestamp = gmdate('H:i:s');
        $logMessage = "[{$timestamp}] [info] {$message}\n";

        (new self($logMessage))->execute();

    }

    public static function warning(string $message): void
    {
        $timestamp = gmdate('H:i:s');
        $logMessage = "[{$timestamp}] [warning] {$message}\n";

        (new self($logMessage))->execute();
    }

    public static function error(string $message): void
    {
        $timestamp = gmdate('H:i:s');
        $logMessage = "[{$timestamp}] [error] {$message}\n";

        (new self($logMessage))->execute();
    }

    public static function startDocumentum(): void
    {
        self::info('======= STARTING DOCUMENT GENERATION PROCESS =======');
    }

    public static function endDocumentum(): void
    {
        self::info('======== END OF DOCUMENT GENERATION PROCESS ========');
    }

    private function execute(): void
    {
        file_put_contents($this->fileName, $this->logMessage, FILE_APPEND);
    }
}
