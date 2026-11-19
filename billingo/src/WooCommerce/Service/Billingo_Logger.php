<?php

namespace App\Billingo\WooCommerce\Service;

class Billingo_Logger
{
    private string $fileName;
    private string $date;

    private static ?string $logDirectory = null;

    private function __construct(private readonly string $logMessage)
    {
        $logdir = self::getLogDirectory();

        $this->date = gmdate('Y-m-d');
        $this->fileName = "{$logdir}/{$this->date}.txt";
    }

    /**
     * A naplók mappája. A naplók személyes adatot (név, cím, e-mail, adószám) és API-válaszokat tartalmaznak,
     * ezért a mappa NEM a bővítmény könyvtárában van (ott az .htaccess nginx alatt hatástalan, és a bővítmény
     * frissítése is törölné), hanem a feltöltések alatt, a webhelyre egyedi, kitalálhatatlan nevű mappában.
     * Ha a feltöltések mappája nem érhető el, a bővítmény saját log/ mappája a tartalék.
     */
    public static function getLogDirectory(): string
    {
        if (self::$logDirectory !== null) {
            return self::$logDirectory;
        }

        $legacyDir = rtrim(str_replace(chr(92), '/', BILLINGO__PLUGIN_DIR), '/') . '/log';
        $dir = $legacyDir;

        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir(null, false);

            if (empty($uploads['error']) && !empty($uploads['basedir'])) {
                $dir = rtrim(str_replace(chr(92), '/', $uploads['basedir']), '/') . '/billingo-logs-' . self::directorySuffix();
            }
        }

        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                mkdir($dir, 0755, true);
            }
        }

        self::protectLogDirectory($dir);

        if ($dir !== $legacyDir) {
            self::migrateLegacyLogs($legacyDir, $dir);
        }

        return self::$logDirectory = $dir;
    }

    /**
     * Webhelyenként egyedi, a titkos sóból képzett, kívülről nem kitalálható mappanév-végződés.
     */
    private static function directorySuffix(): string
    {
        $secret = function_exists('wp_hash') ? wp_hash('billingo-logs') : md5(BILLINGO__PLUGIN_DIR . 'billingo-logs');

        return substr($secret, 0, 16);
    }

    /**
     * Védelem több rétegben: Apache (.htaccess), IIS (web.config) és könyvtárlistázás elleni index.php.
     * Nginx alatt a kitalálhatatlan mappanév védi a fájlokat.
     */
    private static function protectLogDirectory(string $logdir): void
    {
        $files = [
            '.htaccess' => "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
            'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
            'index.php' => "<?php\n// Silence is golden.\n",
        ];

        foreach ($files as $name => $content) {
            $path = "{$logdir}/{$name}";

            if (!file_exists($path)) {
                @file_put_contents($path, $content);
            }
        }
    }

    /**
     * A korábbi (a bővítmény mappájában tárolt, webről elérhető lehetett) naplófájlok áthelyezése az új mappába.
     */
    private static function migrateLegacyLogs(string $legacyDir, string $newDir): void
    {
        if (!is_dir($legacyDir)) {
            return;
        }

        foreach ((array)glob($legacyDir . '/*.txt') as $file) {
            $target = $newDir . '/' . basename($file);

            if (file_exists($target)) {
                // Ugyanarra a napra már van új napló: a régi tartalmát elé fűzzük, majd töröljük a régit.
                $merged = @file_put_contents($target, (string)file_get_contents($file) . (string)file_get_contents($target));
                if ($merged !== false) {
                    @unlink($file);
                }

                continue;
            }

            if (!@rename($file, $target) && @copy($file, $target)) {
                @unlink($file);
            }
        }
    }

    /**
     * A napló fájljai időrendben (legrégebbi elöl) — a védőfájlokat (.htaccess, index.php, web.config) kihagyva.
     *
     * @return string[] teljes elérési utak
     */
    public static function getLogFiles(): array
    {
        $files = (array)glob(self::getLogDirectory() . '/*.txt');
        sort($files);

        return $files;
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
