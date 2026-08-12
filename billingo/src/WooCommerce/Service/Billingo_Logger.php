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

        self::protectLogDirectory($logdir);

        $this->date = gmdate('Y-m-d');
        $this->fileName = "{$logdir}/{$this->date}.txt";
    }

    /**
     * Megakadályozza, hogy a naplófájlok közvetlenül elérhetőek legyenek a weben keresztül.
     */
    private static function protectLogDirectory(string $logdir): void
    {
        $htaccessFile = "{$logdir}/.htaccess";
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Require all denied\nDeny from all\n");
        }

        $indexFile = "{$logdir}/index.php";
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }
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
