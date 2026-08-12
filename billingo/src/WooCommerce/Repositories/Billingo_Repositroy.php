<?php

namespace App\Billingo\WooCommerce\Repositories;

use App\Billingo\Models\Document\Document;
use App\Billingo\WooCommerce\Controllers\Billingo_Controller;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use wpdb;

class Billingo_Repositroy
{
    const TABLENAME_DOCUMENTS = 'billingo_documents';

    /** Engedélyezett oszlopok (whitelist) */
    private const ALLOWED_COLUMNS = [
        'id',
        'order_id',
        'billingo_id',
        'billingo_number',
        'link',
        'type',
        'canceled_by',
        'api_key',
        'created_at'
    ];

    private wpdb $database;
    private readonly string $tableName;

    /** Dinamikus WHERE összeállításhoz */
    private array $where = [];
    private array $params = [];
    private array $paramTypes = [];
    private bool $showCanceled = false;

    public function __construct(?wpdb $wpdbInstance = null)
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $this->database = $wpdbInstance ?: $wpdb;
        $this->tableName = $this->database->prefix . static::TABLENAME_DOCUMENTS;
        $this->resetQuery();
    }

    /**
     * Biztonságos where: csak előre definiált oszlopokra enged,
     * és előkészített paraméterekkel dolgozik.
     */
    public function where(string $column, ?string $value): self
    {
        $column = trim($column);

        if (!in_array($column, self::ALLOWED_COLUMNS, true)) {
            // ismeretlen oszlopra ne engedjük a lekérdezést
            Billingo_Logger::error("Invalid column in where(): {$column}");
            return $this;
        }

        if (is_null($value)) {
            $this->where[] = "`{$column}` IS NULL";
        } else {
            $this->where[] = "`{$column}` = %s";
            $this->params[] = $value;
            $this->paramTypes[] = '%s';
        }

        return $this;
    }

    /**
     * Rekord(ok) lekérdezése
     */
    public function get(int $index = null): ?array
    {
        // Alap SELECT
        $sql = "SELECT * FROM `{$this->tableName}`";

        // Alapértelmezés: a sztornózottak nélkül
        if (!$this->showCanceled) {
            $this->where('canceled_by', null);
        }

        // WHERE összeillesztése
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        // prepare csak akkor kell, ha van paraméter
        $prepared = !empty($this->params)
            ? $this->database->prepare($sql, $this->params)
            : $sql;

        $queryResult = $this->database->get_results($prepared, ARRAY_A) ?: [];
        $this->resetQuery();

        if ($index === null) {
            return $queryResult;
        }

        return $queryResult[$index] ?? null;
    }

    public function first(): ?array
    {
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function withCanceled(): self
    {
        $this->showCanceled = true;
        return $this;
    }

    /**
     * Beszúrás – formátumokkal
     */
    public function create(array $creating): ?array
    {
        $data = $this->filterToAllowedColumns($creating);
        [$data, $formats] = $this->applyFormats($data);

        $created = $this->database->insert($this->tableName, $data, $formats);

        if (!$created) {
            Billingo_Logger::error('Database save operation: FAILED');
            return null;
        }

        $id = (int)$this->database->insert_id;
        Billingo_Logger::info("Database save operation: SUCCESSFUL, Record ID: {$id}");

        return $this->where('id', (string)$id)->first();
    }

    public function createFromDocument(int $orderId, Document $document): ?array
    {
        $filtered = $this->getDatafromDocument($orderId, $document);
        return is_null($filtered) ? null : $this->create($filtered);
    }

    /**
     * Módosítás – formátumokkal + biztonságos where
     */
    public function update(int $id, array $updating): ?array
    {
        $data = $this->filterToAllowedColumns($updating);
        [$data, $formats] = $this->applyFormats($data);

        $where = ['id' => $id];
        $whereFormat = ['%d'];

        $updated = $this->database->update($this->tableName, $data, $where, $formats, $whereFormat);

        if ($updated === false) {
            Billingo_Logger::error('Database update operation: FAILED');
            return null;
        }

        Billingo_Logger::info("Database update operation: SUCCESSFUL, Record ID: {$id}");
        return $this->where('id', (string)$id)->first();
    }

    public function updateFromDocument(int $id, int $orderId, Document $document): ?array
    {
        $filtered = $this->getDatafromDocument($orderId, $document);
        return is_null($filtered) ? null : $this->update($id, $filtered);
    }

    /**
     * Dokumentum objektumból táblába kerülő mezők
     */
    private function getDatafromDocument(int $orderId, Document $document): ?array
    {
        // A Billingo API válaszában akár egyetlen, a mi (esetleg elavult) validációs
        // szabályainknak meg nem felelő mező is hasError()=true-t eredményezhet, annak
        // ellenére, hogy a bizonylat ténylegesen sikeresen létrejött a Billingo oldalán.
        // Ilyenkor NEM szabad kihagynunk a helyi mentést — csak naplózzuk a hibát, hogy
        // legyen nyoma, de a valós ID/sorszám alapján továbbra is elmentjük a rekordot.
        if ($document->hasError()) {
            // A getErrors() csak a Document SAJÁT (legfelső szintű) hibáját látja — ha a
            // hiba egy beágyazott almodellben van (pl. settings, partner, items), azt csak
            // a rekurzívan bejáró getSelfTest()->getErrors() mutatja meg.
            $nestedErrors = $document->getSelfTest()->getErrors();
            Billingo_Logger::warning(
                'A Billingo válasz nem felelt meg minden validációs szabálynak, de a bizonylat '
                . 'létrejött (ID: ' . $document->id . '), a helyi mentés folytatódik. Részletes hiba: '
                . json_encode($nestedErrors, JSON_PARTIAL_OUTPUT_ON_ERROR)
            );
        }

        if (empty($document->id)) {
            $rawArray = $document->toArray();
            Billingo_Logger::error(
                'A Billingo válaszból hiányzik a bizonylat ID-ja, a helyi mentés kimarad. Order ID: '
                . $orderId . '. Az "id" mező tényleges értéke: ' . var_export($rawArray['id'] ?? '(nincs ilyen kulcs)', true)
                . '. hasError(): ' . var_export($document->hasError(), true)
                . '. Teljes toArray(): ' . json_encode($rawArray, JSON_PARTIAL_OUTPUT_ON_ERROR)
            );

            return null;
        }

        return [
            'order_id'        => $orderId,
            'billingo_id'     => (int)$document->id,
            'billingo_number' => (string)$document->invoice_number,
            'link'            => (string)(new Billingo_Controller($orderId))->getLink($document->id),
            'type'            => (string)$document->type,
            'api_key'         => (string)get_option('wc_billingo_api_key', ''),
            // 'created_at'   => nem kell, default CURRENT_TIMESTAMP
        ];
    }

    /**
     * Query builder reset
     */
    private function resetQuery(): void
    {
        $this->where = [];
        $this->params = [];
        $this->paramTypes = [];
        $this->showCanceled = false;
    }

    /**
     * Csak engedélyezett oszlopok átengedése
     */
    private function filterToAllowedColumns(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
    }

    /**
     * Oszlopokhoz típusformátum rendelése WPDB-hez
     */
    private function applyFormats(array $data): array
    {
        $formatsByColumn = [
            'id'              => '%d',
            'order_id'        => '%d',
            'billingo_id'     => '%d',
            'billingo_number' => '%s',
            'link'            => '%s',
            'type'            => '%s',
            'canceled_by'     => '%d',
            'api_key'         => '%s',
            'created_at'      => '%s',
        ];

        $formats = [];
        foreach ($data as $col => $val) {
            $formats[] = $formatsByColumn[$col] ?? '%s';
        }

        return [$data, $formats];
    }

    /**
     * Telepítés / sémakezelés: dbDelta használata (nem közvetlen ALTER/CREATE)
     */
    public static function install(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLENAME_DOCUMENTS;
        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta betöltése
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta: teljes, idempotens séma
        $sql = "CREATE TABLE {$table_name} (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id INT(11) UNSIGNED NOT NULL,
            billingo_id INT(11) NULL,
            billingo_number VARCHAR(127) NULL,
            link VARCHAR(255) NULL,
            type VARCHAR(32) NULL,
            canceled_by INT(11) NULL DEFAULT NULL,
            api_key VARCHAR(64) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY type (type),
            KEY api_key (api_key)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Szükséges oszlopok érvényesítése: dbDelta-val rendezve
     * (nem SHOW/ALTER közvetlenül)
     */
    public static function validateAndAddMissingColumns(): void
    {
        // Egyszerűen futtassuk le újra az install-t (dbDelta idempotens)
        self::install();
    }
}
