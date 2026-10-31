<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes a complete, restorable SQL dump of the database using nothing but the
 * PDO connection the application already holds.
 *
 * Deliberately does NOT shell out to mysqldump. On the production host the
 * site runs under a hosting panel with no SSH access and a restricted
 * open_basedir, so exec()/proc_open() cannot be relied on and the mysqldump
 * binary is not in a known place. A pure PHP dumper always works there.
 *
 * The output is the shape phpMyAdmin and the panel's "Import dump" expect:
 * DROP TABLE IF EXISTS, CREATE TABLE, then extended INSERTs in batches.
 */
class DatabaseBackupService
{
    /**
     * Rows per INSERT statement, and rows fetched per query.
     *
     * The hosting panel caps PHP memory at 128 MB, so a table is read in
     * slices rather than all at once. 500 keeps the SQL statements readable
     * in a text editor while staying well inside that limit.
     */
    private const ROWS_PER_CHUNK = 500;

    /**
     * Write a dump of every table to $path.
     *
     * @return array{tables:int, rows:int, bytes:int, driver:string}
     */
    public function writeTo(string $path): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException(
                "Backups are not supported for the \"{$driver}\" database driver."
            );
        }

        $pdo = $connection->getPdo();
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path} for writing.");
        }

        $tables = $this->tables($driver);
        $rowCount = 0;

        try {
            $this->writeHeader($handle, $driver, count($tables));

            foreach ($tables as $table) {
                $rowCount += $this->writeTable($handle, $pdo, $driver, $table);
            }

            $this->writeFooter($handle, $driver);
        } finally {
            fclose($handle);
        }

        $bytes = (int) @filesize($path);

        if ($bytes <= 0) {
            @unlink($path);

            throw new RuntimeException('The backup came out empty; nothing was written.');
        }

        return [
            'tables' => count($tables),
            'rows' => $rowCount,
            'bytes' => $bytes,
            'driver' => $driver,
        ];
    }

    /** @return array<int, string> */
    private function tables(string $driver): array
    {
        if ($driver === 'sqlite') {
            $rows = DB::select(
                "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
            );

            return array_map(static fn ($row) => (string) $row->name, $rows);
        }

        // MySQL: base tables only, so views are not mistaken for tables and
        // dumped twice.
        $rows = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        return array_map(static function ($row) {
            $values = array_values((array) $row);

            return (string) $values[0];
        }, $rows);
    }

    /** @return int rows written for this table */
    private function writeTable($handle, \PDO $pdo, string $driver, string $table): int
    {
        $quoted = $this->quoteIdentifier($table, $driver);

        fwrite($handle, "\n--\n-- Table structure for `{$table}`\n--\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");

        $create = $this->createStatement($driver, $table);

        if ($create === null) {
            // Should not happen, but an unreadable structure is better skipped
            // loudly than dumped as a table that cannot be recreated.
            fwrite($handle, "-- (skipped: could not read the table structure)\n");

            return 0;
        }

        fwrite($handle, $create . ";\n");

        $written = 0;
        $offset = 0;

        while (true) {
            $rows = DB::select("SELECT * FROM {$quoted} LIMIT " . self::ROWS_PER_CHUNK . " OFFSET {$offset}");

            if ($rows === []) {
                break;
            }

            fwrite($handle, $this->insertStatement($pdo, $driver, $table, $rows));
            $written += count($rows);
            $offset += self::ROWS_PER_CHUNK;

            if (count($rows) < self::ROWS_PER_CHUNK) {
                break;
            }
        }

        fwrite($handle, "\n-- Rows in `{$table}`: {$written}\n");

        return $written;
    }

    private function createStatement(string $driver, string $table): ?string
    {
        if ($driver === 'sqlite') {
            $rows = DB::select(
                "select sql from sqlite_master where type = 'table' and name = ?",
                [$table]
            );

            $sql = $rows[0]->sql ?? null;

            return $sql ? trim((string) $sql) : null;
        }

        $rows = DB::select('SHOW CREATE TABLE ' . $this->quoteIdentifier($table, $driver));

        if ($rows === []) {
            return null;
        }

        $values = array_values((array) $rows[0]);

        // SHOW CREATE TABLE returns (Table, "Create Table").
        return isset($values[1]) ? trim((string) $values[1]) : null;
    }

    /** @param array<int, object|array> $rows */
    private function insertStatement(\PDO $pdo, string $driver, string $table, array $rows): string
    {
        $columns = array_keys((array) $rows[0]);
        $columnList = implode(', ', array_map(
            fn ($column) => $this->quoteIdentifier((string) $column, $driver),
            $columns
        ));

        $tuples = [];

        foreach ($rows as $row) {
            $values = array_map(
                fn ($value) => $this->literal($pdo, $value),
                array_values((array) $row)
            );

            $tuples[] = '(' . implode(', ', $values) . ')';
        }

        return "INSERT INTO {$this->quoteIdentifier($table, $driver)} ({$columnList}) VALUES\n"
            . implode(",\n", $tuples) . ";\n";
    }

    private function literal(\PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // No scientific notation and no locale-dependent separator: SQL
            // importers read a full stop and nothing else.
            return rtrim(rtrim(sprintf('%.14F', $value), '0'), '.') ?: '0';
        }

        $string = (string) $value;

        // Blobs and legacy rows can hold bytes that are not valid UTF-8.
        // They cannot be quoted as text without corrupting them, so write
        // them as a hex literal instead, which restores byte for byte.
        if (!mb_check_encoding($string, 'UTF-8')) {
            return '0x' . bin2hex($string);
        }

        return $pdo->quote($string);
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        return $driver === 'sqlite'
            ? '"' . str_replace('"', '""', $identifier) . '"'
            : '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function writeHeader($handle, string $driver, int $tableCount): void
    {
        $generated = now()->format('Y-m-d H:i:s');
        $app = config('app.name', 'PMAS');

        fwrite($handle, "-- {$app} database backup\n");
        fwrite($handle, "-- Generated: {$generated}\n");
        fwrite($handle, "-- Driver: {$driver}\n");
        fwrite($handle, "-- Tables: {$tableCount}\n");
        fwrite($handle, "--\n");
        fwrite($handle, "-- Restore with the hosting panel's Import dump, or phpMyAdmin's\n");
        fwrite($handle, "-- Import tab. Restoring replaces everything currently in the\n");
        fwrite($handle, "-- database, so make sure that is what you want.\n");
        fwrite($handle, "--\n\n");

        if ($driver === 'sqlite') {
            fwrite($handle, "PRAGMA foreign_keys = OFF;\n\n");

            return;
        }

        fwrite($handle, "SET NAMES utf8mb4;\n");
        // Tables are dumped alphabetically, so a child table can be created
        // before its parent. Deferring foreign key checks makes the dump
        // importable in any order.
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($handle, "SET UNIQUE_CHECKS = 0;\n\n");
    }

    private function writeFooter($handle, string $driver): void
    {
        fwrite($handle, "\n");

        if ($driver === 'sqlite') {
            fwrite($handle, "PRAGMA foreign_keys = ON;\n");

            return;
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($handle, "SET UNIQUE_CHECKS = 1;\n");
    }
}
