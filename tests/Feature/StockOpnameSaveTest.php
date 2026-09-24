<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Role;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StockOpnameSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(
            ['code' => 'superadmin'], ['name' => 'Superadmin']
        ));
        $this->actingAs($user);
    }

    public function test_adjustments_apply_immediately_and_can_be_edited_and_deleted(): void
    {
        $medicine = Medicine::query()->create(['code' => 'ADJ-MED', 'name' => 'Obat batch', 'small_unit' => 'Tablet', 'is_active' => true]);
        $opname = StockOpname::query()->create(['opname_number' => 'OP-ADJ', 'opname_date' => '2026-09-11', 'status' => 'draft']);
        $item = $opname->items()->create(['medicine_id' => $medicine->id, 'system_quantity' => 10, 'physical_quantity' => 8, 'difference_quantity' => -2, 'adjustment_value' => -2000]);
        $batches = collect();
        foreach (['BATCH-A' => 6, 'BATCH-B' => 4] as $number => $quantity) {
            $batches->push(StockBatch::query()->create([
                'medicine_id' => $medicine->id, 'batch_number' => $number, 'received_at' => '2026-09-01',
                'purchase_price' => 1000, 'initial_quantity' => $quantity, 'quantity_in' => $quantity,
                'quantity_out' => 0, 'quantity_balance' => $quantity, 'status' => 'active',
            ]));
        }
        $payload = ['opname_item_id' => $item->id, 'adjustment_number' => 'ADJ-001', 'adjustment_date' => '2026-09-11', 'settlement_type' => 'writeoff',
            'batches' => [['batch_number' => 'BATCH-A', 'quantity' => 5], ['batch_number' => 'BATCH-B', 'quantity' => 3]]];
        $url = route('stok-batch.penyesuaian-stok.follow-up.store', $item);
        $documentResponse = $this->get(route('stok-batch.penyesuaian-stok.dokumen', $opname))
            ->assertOk()
            ->assertSee('Penyesuaian Stok — No Opname OP-ADJ')
            ->assertSee('BATCH-A')
            ->assertSee('BATCH-B')
            ->assertSeeInOrder(['Kode', 'Obat', 'Batch', 'Lokasi'])
            ->assertSeeInOrder(['ADJ-MED', 'Obat batch', 'BATCH-A'])
            ->assertSee('Jenis penyesuaian')
            ->assertSee('x-model="settlementType"', false)
            ->assertDontSee('Proses tindak lanjut')
            ->assertDontSee('Detail penyelesaian')
            ->assertDontSee('>Simpan<', false)
            ->assertDontSee('Hilang 2')
            ->assertDontSee('name="notes"', false)
            ->assertDontSee('Penyesuaian Obat batch');
        $this->assertSame(2, substr_count($documentResponse->getContent(), 'Obat batch'));
        $partialPayload = $payload;
        $partialPayload['batches'][1]['quantity'] = '';
        $this->postJson($url, $partialPayload)
            ->assertOk()
            ->assertJsonPath('message', 'Stok fisik berhasil disimpan.')
            ->assertJsonPath('applied', false);
        $this->assertDatabaseHas('stock_adjustment_follow_up_batches', ['quantity' => 5]);
        $this->get(route('stok-batch.penyesuaian-stok.dokumen', $opname))
            ->assertOk()->assertSee('value="5"', false);
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertSessionHas('toast.type', 'success');
        $this->assertSame('draft', $item->fresh()->followUp->status);
        $this->assertEquals(6, $batches[0]->fresh()->quantity_balance);
        $this->assertEquals(4, $batches[1]->fresh()->quantity_balance);
        $this->post(route('stok-batch.penyesuaian-stok.apply', $opname))->assertSessionHas('toast.type', 'success');
        $this->assertSame('applied', $item->fresh()->followUp->status);
        $this->assertEquals(5, $batches[0]->fresh()->quantity_balance);
        $this->assertEquals(3, $batches[1]->fresh()->quantity_balance);
        $this->get(route('laporan.laporan-hilang-biasa', ['date_from' => '2026-09-11', 'date_to' => '2026-09-11']))
            ->assertOk()
            ->assertSee('OP-ADJ')
            ->assertSee(route('stok-batch.penyesuaian-stok.dokumen', $opname));
        $this->get(route('stok-batch.penyesuaian-stok.dokumen', $opname))
            ->assertOk()->assertSee('value="5"', false)->assertSee('value="3"', false);
        $this->assertFalse(Route::has('stok-batch.penyesuaian-stok.history'));
        $this->post(route('stok-batch.penyesuaian-stok.restore', $opname))->assertSessionHas('toast.type', 'success');
        $this->assertSame('draft', $item->fresh()->followUp->status);
        $this->assertEquals(6, $batches[0]->fresh()->quantity_balance);
        $this->assertEquals(4, $batches[1]->fresh()->quantity_balance);
        $payload['batches'][0]['quantity'] = 4;
        $payload['batches'][1]['quantity'] = 4;
        $this->post($url, $payload)->assertSessionHas('toast.type', 'success');
        $this->post(route('stok-batch.penyesuaian-stok.apply', $opname))->assertSessionHas('toast.type', 'success');
        $this->assertEquals(4, $batches[0]->fresh()->quantity_balance);
        $this->assertEquals(4, $batches[1]->fresh()->quantity_balance);
        $this->assertDatabaseCount('stock_adjustment_follow_ups', 1);

        StockMovement::query()->create(['movement_date' => now(), 'movement_type' => 'sale', 'medicine_id' => $medicine->id,
            'stock_batch_id' => $batches[0]->id, 'quantity_in' => 0, 'quantity_out' => 1, 'balance_after' => 3, 'unit_cost' => 1000]);
        $batches[0]->update(['quantity_balance' => 3, 'quantity_out' => 3]);
        $this->post(route('stok-batch.penyesuaian-stok.restore', $opname))->assertSessionHas('toast.type', 'error');
        $this->assertEquals(3, $batches[0]->fresh()->quantity_balance);
        $this->assertSame('applied', $item->fresh()->followUp->status);
        $this->delete(route('stok-batch.stok-opname.destroy', $opname))
            ->assertSessionHas('toast.type', 'error');
        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id]);
        $this->assertEquals(3, $batches[0]->fresh()->quantity_balance);
        $this->get(route('stok-batch.stok-opname.edit', $opname))->assertOk();
        $this->put(route('stok-batch.stok-opname.update', $opname), [
            'opname_number' => $opname->opname_number,
            'opname_date' => '2026-09-11',
            'items' => [['medicine_id' => $medicine->id, 'system_quantity' => 10, 'physical_quantity' => 7, 'average_unit_cost' => 1000]],
        ])->assertSessionHasErrors();
        $this->assertEquals(8, $item->fresh()->physical_quantity);
        $this->assertEquals(3, $batches[0]->fresh()->quantity_balance);
        $this->assertSame('applied', $item->fresh()->followUp->status);
    }

    public function test_stock_opname_hides_empty_batches_but_keeps_their_history(): void
    {
        $location = StorageLocation::query()->create(['code' => 'LOC-0001', 'name' => 'Apotek', 'is_active' => true]);
        $medicine = Medicine::query()->create(['code' => 'EMPTY-BATCH', 'name' => 'Obat Batch Habis', 'small_unit' => 'Tablet', 'is_active' => true]);
        $batch = StockBatch::query()->create([
            'medicine_id' => $medicine->id, 'storage_location_id' => $location->id, 'batch_number' => 'HABIS-001',
            'received_at' => '2026-09-01', 'purchase_price' => 1000, 'initial_quantity' => 10,
            'quantity_in' => 10, 'quantity_out' => 10, 'quantity_balance' => 0, 'status' => 'empty',
        ]);
        StockMovement::query()->create([
            'movement_date' => now(), 'movement_type' => 'stock_opname_loss', 'medicine_id' => $medicine->id,
            'stock_batch_id' => $batch->id, 'quantity_out' => 10, 'balance_after' => 0, 'unit_cost' => 1000,
        ]);

        $this->get(route('stok-batch.stok-opname'))
            ->assertOk()
            ->assertSee('Obat Batch Habis')
            ->assertViewHas('rows', fn ($rows) => $rows->sole('medicine_id', $medicine->id)['batch_count'] === 0)
            ->assertDontSee('HABIS-001');
        $this->assertDatabaseHas('stock_batches', ['id' => $batch->id, 'quantity_balance' => 0, 'status' => 'empty']);
        $this->assertDatabaseHas('stock_movements', ['stock_batch_id' => $batch->id, 'movement_type' => 'stock_opname_loss']);
    }

    public function test_second_same_day_opname_appends_other_medicine_to_the_same_document(): void
    {
        $firstMedicine = Medicine::query()->create(['code' => 'SO-FIRST', 'name' => 'Obat Pertama', 'small_unit' => 'Tablet', 'is_active' => true]);
        $secondMedicine = Medicine::query()->create(['code' => 'SO-SECOND', 'name' => 'Obat Susulan', 'small_unit' => 'Tablet', 'is_active' => true]);

        $this->post(route('stok-batch.stok-opname.store'), [
            'opname_number' => 'SO-20260912-001', 'opname_date' => '2026-09-12',
            'items_payload' => json_encode([['medicine_id' => $firstMedicine->id, 'system_quantity' => 10, 'physical_quantity' => 10, 'average_unit_cost' => 1000]]),
        ])->assertSessionHasNoErrors();
        $this->post(route('stok-batch.stok-opname.store'), [
            'opname_number' => 'SO-20260912-002', 'opname_date' => '2026-09-12',
            'items_payload' => json_encode([['medicine_id' => $secondMedicine->id, 'system_quantity' => 8, 'physical_quantity' => 8, 'average_unit_cost' => 1000]]),
        ])->assertSessionHas('toast.message', 'Data stok opname berhasil disimpan ke dokumen hari ini.');

        $this->assertDatabaseCount('stock_opnames', 1);
        $this->assertDatabaseHas('stock_opnames', ['opname_number' => 'SO-20260912-001']);
        $this->assertDatabaseCount('stock_opname_items', 2);
    }

    public function test_second_same_day_opname_rejects_a_medicine_already_in_the_document(): void
    {
        $medicine = Medicine::query()->create([
            'code' => 'SO-REPEAT',
            'name' => 'Obat Diopname Ulang',
            'small_unit' => 'Tablet',
            'is_active' => true,
        ]);

        $this->post(route('stok-batch.stok-opname.store'), [
            'opname_number' => 'SO-20260912-001',
            'opname_date' => '2026-09-12',
            'items_payload' => json_encode([[
                'medicine_id' => $medicine->id,
                'system_quantity' => 10,
                'physical_quantity' => 10,
                'average_unit_cost' => 1000,
            ]]),
        ])->assertSessionHasNoErrors();

        $this->post(route('stok-batch.stok-opname.store'), [
            'opname_number' => 'SO-20260912-002',
            'opname_date' => '2026-09-12',
            'items_payload' => json_encode([[
                'medicine_id' => $medicine->id,
                'system_quantity' => 10,
                'physical_quantity' => 7,
                'average_unit_cost' => 1000,
            ]]),
        ])->assertSessionHas('toast', [
            'type' => 'error',
            'message' => 'Obat Obat Diopname Ulang sudah tercatat pada stok opname hari ini.',
        ]);

        $this->assertDatabaseCount('stock_opnames', 1);
        $this->assertDatabaseCount('stock_opname_items', 1);
        $this->assertDatabaseHas('stock_opname_items', [
            'medicine_id' => $medicine->id,
            'physical_quantity' => 10,
            'difference_quantity' => 0,
        ]);
    }

    public function test_json_worksheet_saves_all_counted_rows_including_zero(): void
    {
        $items = [];
        foreach ([0, 12] as $index => $quantity) {
            $medicine = Medicine::query()->create([
                'code' => 'OP-'.$index,
                'name' => 'Obat '.$index,
                'small_unit' => 'Tablet',
                'is_active' => true,
            ]);
            $items[$index + 200] = [
                'medicine_id' => $medicine->id,
                'system_quantity' => 10,
                'physical_quantity' => $quantity,
                'average_unit_cost' => 1000,
            ];
        }

        $this->post(route('stok-batch.stok-opname.store'), [
            'opname_number' => 'OP-TEST-001',
            'opname_date' => '2026-09-11',
            'items_payload' => json_encode($items),
        ])->assertSessionHasNoErrors()->assertRedirect(route('stok-batch.stok-opname'));

        $this->assertDatabaseCount('stock_opname_items', 2);
        foreach ($items as $item) {
            $this->assertDatabaseHas('stock_opname_items', [
                'medicine_id' => $item['medicine_id'],
                'physical_quantity' => $item['physical_quantity'],
                'difference_quantity' => $item['physical_quantity'] - 10,
            ]);
        }

        $opname = StockOpname::query()->sole();
        $opname->update(['notes' => 'Catatan awal']);
        $this->get(route('stok-batch.stok-opname.edit', $opname))
            ->assertOk()
            ->assertSee('Catatan awal')
            ->assertSee('Simpan Perubahan')
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 2
                && $rows[0]['physical_quantity'] === 0.0
                && $rows[1]['physical_quantity'] === 12.0
                && $rows[0]['system_quantity'] === 10.0);

        $items[200]['physical_quantity'] = 8;
        $this->put(route('stok-batch.stok-opname.update', $opname), [
            'opname_number' => $opname->opname_number,
            'opname_date' => '2026-09-11',
            'notes' => 'Sudah dikoreksi',
            'items_payload' => json_encode($items),
        ])->assertSessionHasNoErrors()->assertRedirect(route('stok-batch.stok-opname.draft'));
        $this->assertDatabaseCount('stock_opnames', 1);
        $this->assertDatabaseCount('stock_opname_items', 2);
        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'medicine_id' => $items[200]['medicine_id'],
            'physical_quantity' => 8,
            'difference_quantity' => -2,
            'adjustment_value' => -2000,
        ]);
        $this->assertSame('Sudah dikoreksi', $opname->fresh()->notes);

        $this->get(route('stok-batch.penyesuaian-stok', ['date_from' => '2026-09-11', 'date_to' => '2026-09-11']))
            ->assertOk()->assertSee($opname->opname_number);
        $this->get(route('stok-batch.penyesuaian-stok.dokumen', $opname))->assertOk();
        $this->get(route('stok-batch.stok-opname.draft', ['date_from' => '2026-09-11', 'date_to' => '2026-09-11']))
            ->assertOk()->assertDontSee('Approve')->assertSee('Edit');
        $this->assertFalse(Route::has('stok-batch.stok-opname.approve'));

        $opname->items()->first()->followUp()->create([
            'adjustment_number' => 'ADJ-TEST-001',
            'adjustment_date' => '2026-09-11',
            'difference_type' => 'loss',
            'status' => 'draft',
        ]);
        $this->get(route('stok-batch.stok-opname.edit', $opname))->assertOk()->assertViewIs('stocks.opname');
        $this->get(route('stok-batch.stok-opname.draft', ['date_from' => '2026-09-11', 'date_to' => '2026-09-11']))
            ->assertOk()->assertSee(route('stok-batch.stok-opname.edit', $opname));
        $editPayload = [
            'opname_number' => $opname->opname_number,
            'opname_date' => '2026-09-11',
            'items_payload' => json_encode($items),
        ];
        $this->put(route('stok-batch.stok-opname.update', $opname), $editPayload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_adjustment_follow_ups', ['adjustment_number' => 'ADJ-TEST-001']);
        $items[200]['physical_quantity'] = 7;
        $editPayload['items_payload'] = json_encode($items);
        $this->put(route('stok-batch.stok-opname.update', $opname), $editPayload)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('stock_adjustment_follow_ups', ['adjustment_number' => 'ADJ-TEST-001']);
        $this->assertDatabaseHas('stock_opname_items', ['medicine_id' => $items[200]['medicine_id'], 'physical_quantity' => 7]);
        $this->assertDatabaseCount('stock_opname_items', 2);
    }

    public function test_missing_optional_physical_quantity_does_not_crash(): void
    {
        $medicine = Medicine::query()->create([
            'code' => 'OP-EMPTY', 'name' => 'Obat', 'small_unit' => 'Tablet', 'is_active' => true,
        ]);

        $this->from(route('stok-batch.stok-opname'))
            ->post(route('stok-batch.stok-opname.store'), [
                'opname_number' => 'OP-TEST-002',
                'opname_date' => '2026-09-11',
                'items_payload' => json_encode([['medicine_id' => $medicine->id, 'system_quantity' => 10]]),
            ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('stock_opnames', 0);
    }
}
