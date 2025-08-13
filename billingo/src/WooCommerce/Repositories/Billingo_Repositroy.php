<?php

namespace App\Billingo\WooCommerce\Repositories;

use App\Billingo\Models\Document\Document;
use App\Billingo\WooCommerce\Controllers\Billingo_Controller;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use wpdb;

class Billingo_Repositroy
{
    const TABLENAME_DOCUMENTS = 'billingo_documents';

    private wpdb $database;
    private readonly string $tableName;
    private ?string $query;
    private bool $showCanceled = false;

    public function __construct()
    {
        global $wpdb;

        $this->database = $wpdb;
        $this->tableName = $this->database->prefix . static::TABLENAME_DOCUMENTS;
    }

    public function where(string $property, ?string $value): self
    {
        if (empty($this->query)) {
            $this->query = is_null($value)
                ? "{$property} IS NULL"
                : "{$property} = '{$value}'";
        } else {
            $this->query .= is_null($value)
                ? " AND {$property} IS NULL"
                : " AND {$property} = '{$value}'";
        }

        return $this;
    }

    public function get(int $index = null): ?array
    {
        $sql = "SELECT *
                FROM {$this->tableName}";

        if (!$this->showCanceled) {
            $this->where('canceled_by', null);
        }

        if (!empty($this->query)) {

            $sql .= " WHERE {$this->query}";
        }

        $queryResult = $this->database->get_results($sql, ARRAY_A);
        $this->setDefault();

        return is_null($index)
            ? $queryResult
            : $queryResult[$index] ?? null;
    }

    public function first(): ?array
    {
        return $this->get(0);
    }

    public function withCanceled(): self
    {
        $this->showCanceled = true;

        return $this;
    }

    public function create(array $creating): ?array
    {
        $created = $this->database->insert(
            $this->tableName,
            $creating);

        if (!$created) {

            Billingo_Logger::error('Database save operation: FAILED');

            return null;
        }

        Billingo_Logger::info("Database save operation: SUCCESSFUL, Record ID: {$this->database->insert_id}");

        return $this->where('id', $this->database->insert_id)->first();
    }

    public function createFromDocument(int $orderId, Document $document): ?array
    {
        $filtered = $this->getDatafromDocument($orderId, $document);
        return is_null($filtered) ? null : $this->create($filtered);
    }

    public function update(int $id, array $updating): ?array
    {
        $updated = $this->database->update(
            $this->tableName,
            $updating,
            ['id' => $id]
        );

        if ($updated === false) {

            Billingo_Logger::error('Database update operation: FAILED');

            return null;
        }

        Billingo_Logger::info("Database update operation: SUCCESSFUL, Record ID: {$id}");

        return $this->where('id', $id)->first();
    }

    public function updateFromDocument(int $id, int $orderId, Document $document): ?array
    {
        $filtered = $this->getDatafromDocument($orderId, $document);

        return is_null($filtered) ? null : $this->update($id, $filtered);
    }

    private function getDatafromDocument(int $orderId, Document $document): ?array
    {
        if ($document->hasError() && $document->getErrors()) {
            return null;
        }

        return [
            'order_id' => $orderId,
            "billingo_id" => $document->id,
            "billingo_number" => $document->invoice_number,
            "link" => (new Billingo_Controller($orderId))->getLink($document->id),
            "type" => $document->type,
            "api_key" => get_option('wc_billingo_api_key', ''),
        ];
    }

    private function setDefault(): void
    {
        $this->query = null;
        $this->showCanceled = false;
    }

    public static function install(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLENAME_DOCUMENTS;

        $wpdb->query('CREATE TABLE IF NOT EXISTS `' . $table_name . '`(
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, 
              `order_id` INT(11) UNSIGNED NOT NULL, 
              `billingo_id` INT(11) NULL, 
              `billingo_number` VARCHAR(127) NULL, 
              `link` VARCHAR(255) NULL, 
              `type` VARCHAR(32) NULL, 
              `canceled_by` INT(11) NULL DEFAULT NULL, 
              `api_key` VARCHAR(64) NULL, 
              `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY(`order_id`), 
            KEY(`type`),
            KEY(`api_key`));');

        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$table_name` LIKE %s", 'canceled_by'
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `$table_name` ADD `canceled_by` INT(11) NULL DEFAULT NULL;");
        }
    }

    /**
     * Check for missing columns and add them if they don't exist
     */
    public static function validateAndAddMissingColumns(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLENAME_DOCUMENTS;
        
        // Define required columns with their definitions
        $required_columns = [
            'id' => 'INT(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'order_id' => 'INT(11) UNSIGNED NOT NULL',
            'billingo_id' => 'INT(11) NULL',
            'billingo_number' => 'VARCHAR(127) NULL',
            'link' => 'VARCHAR(255) NULL',
            'type' => 'VARCHAR(32) NULL',
            'canceled_by' => 'INT(11) NULL DEFAULT NULL',
            'api_key' => 'VARCHAR(64) NULL',
            'created_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
        ];
        
        // Get existing columns
        $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM `$table_name`", ARRAY_A);
        $existing_column_names = array_column($existing_columns, 'Field');
        
        // Add missing columns
        foreach ($required_columns as $column_name => $column_definition) {
            if (!in_array($column_name, $existing_column_names)) {
                $sql = "ALTER TABLE `$table_name` ADD `$column_name` $column_definition";
                $result = $wpdb->query($sql);
                
                if ($result !== false) {
                    Billingo_Logger::info("Successfully added column '$column_name' to billingo_documents table");
                } else {
                    Billingo_Logger::error("Failed to add column '$column_name' to billingo_documents table");
                }
                
                // Migration for the created_at column: if date_add column exists, update created_at with date_add values
                if ($column_name === 'created_at') {
                    self::migrateFromDateAddToCreatedAt($table_name, $existing_column_names);
                }
            }
        }
    }
    
    /**
     * Migrate data from date_add column to created_at column if date_add exists
     */
    private static function migrateFromDateAddToCreatedAt(string $table_name, array $existing_columns): void
    {
        global $wpdb;
        
        // Check if date_add column exists
        if (in_array('date_add', $existing_columns)) {
            //update if the date_add value is not null, and the created_at value is bigger than the date_add value
            $update_sql = "UPDATE `$table_name` SET `created_at` = `date_add` WHERE `created_at` > `date_add` AND `date_add` IS NOT NULL";
            $result = $wpdb->query($update_sql);
            
            if ($result !== false) {
                Billingo_Logger::info("Successfully migrated $result records from date_add to created_at column");
            } else {
                Billingo_Logger::error("Failed to migrate data from date_add to created_at column");
            }
        }
    }
}
