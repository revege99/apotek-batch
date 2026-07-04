<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionDataResetService
{
    /**
     * Transaction tables grouped by module.
     *
     * @return array<string, array<int, string>>
     */
    public function groupedTables(): array
    {
        return [
            'Pembelian' => [
                'purchase_exchange_replacement_items',
                'purchase_exchange_replacements',
                'purchase_exchange_items',
                'purchase_exchanges',
                'purchase_return_replacement_items',
                'purchase_return_replacements',
                'purchase_return_items',
                'purchase_returns',
                'supplier_payment_allocations',
                'supplier_payments',
                'purchase_invoice_items',
                'purchase_invoices',
            ],
            'Penjualan' => [
                'customer_payments',
                'sale_return_items',
                'sale_returns',
                'sale_items',
                'sales',
            ],
            'Stok & Batch' => [
                'stock_adjustment_recovery_payments',
                'stock_adjustment_recoveries',
                'stock_adjustment_follow_up_batches',
                'stock_adjustment_follow_ups',
                'stock_opname_items',
                'stock_opnames',
                'stock_movements',
                'stock_batches',
            ],
        ];
    }

    /**
     * Data scopes preserved by the cleanup feature.
     *
     * @return array<int, string>
     */
    public function preservedScopes(): array
    {
        return [
            'Semua menu di Master Data',
            'Profil apotik dan setting aplikasi',
            'User, role, dan hak akses',
            'Data lisensi dan pengaturan QRIS lisensi',
        ];
    }

    /**
     * Summary counts for the cleanup dashboard.
     *
     * @return array<string, array{rows:int,tables:int}>
     */
    public function summary(): array
    {
        return collect($this->groupedTables())
            ->map(function (array $tables): array {
                $rows = collect($tables)
                    ->sum(fn (string $table): int => $this->safeCount($table));

                return [
                    'rows' => $rows,
                    'tables' => count($tables),
                ];
            })
            ->all();
    }

    /**
     * Purge all non-master transactional data and return deleted row counts.
     *
     * @return array<string, int>
     */
    public function purge(): array
    {
        $deleted = [];
        $driver = DB::getDriverName();
        $tables = collect($this->groupedTables())
            ->flatten()
            ->values()
            ->all();

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                $deleted[$table] = $this->safeCount($table);
                DB::table($table)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        foreach ($tables as $table) {
            $this->resetIdentity($table, $driver);
        }

        return $deleted;
    }

    private function safeCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }

    private function resetIdentity(string $table, string $driver): void
    {
        match ($driver) {
            'mysql' => DB::statement(sprintf('ALTER TABLE `%s` AUTO_INCREMENT = 1', $table)),
            'sqlite' => DB::table('sqlite_sequence')->where('name', $table)->delete(),
            'pgsql' => DB::statement(sprintf('ALTER SEQUENCE %s_id_seq RESTART WITH 1', $table)),
            default => null,
        };
    }
}
