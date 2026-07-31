<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that retain a medicine snapshot.
     *
     * @var list<string>
     */
    private array $tables = [
        'purchase_invoice_items',
        'purchase_return_items',
        'purchase_return_replacement_items',
        'purchase_exchange_items',
        'purchase_exchange_replacement_items',
        'sale_items',
        'sale_return_items',
        'stock_batches',
        'stock_movements',
        'stock_opname_items',
        'opening_stock_entry_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('medicine_code_snapshot', 100)->nullable()->after('medicine_id');
                $table->string('medicine_name_snapshot')->nullable()->after('medicine_code_snapshot');
                $table->string('medicine_unit_snapshot', 50)->nullable()->after('medicine_name_snapshot');
            });
        }

        $this->backfillSnapshots();

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['medicine_id']);
                $table->unsignedBigInteger('medicine_id')->nullable()->change();
                $table->foreign('medicine_id')->references('id')->on('medicines')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['medicine_id']);
                $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
                $table->dropColumn([
                    'medicine_code_snapshot',
                    'medicine_name_snapshot',
                    'medicine_unit_snapshot',
                ]);
            });
        }
    }

    private function backfillSnapshots(): void
    {
        foreach ($this->tables as $tableName) {
            DB::table($tableName)
                ->whereNotNull('medicine_id')
                ->select(['id', 'medicine_id'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName): void {
                    $medicines = DB::table('medicines')
                        ->whereIn('id', $rows->pluck('medicine_id')->filter()->unique()->all())
                        ->get(['id', 'code', 'name', 'small_unit'])
                        ->keyBy('id');

                    foreach ($rows as $row) {
                        $medicine = $medicines->get($row->medicine_id);

                        if ($medicine === null) {
                            continue;
                        }

                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update([
                                'medicine_code_snapshot' => $medicine->code,
                                'medicine_name_snapshot' => $medicine->name,
                                'medicine_unit_snapshot' => $medicine->small_unit,
                            ]);
                    }
                });
        }
    }
};
