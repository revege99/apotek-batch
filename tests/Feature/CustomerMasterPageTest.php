<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMasterPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_customer_group_master_page(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::query()->create([
            'code' => 'GPL-0001',
            'name' => 'Klinik',
            'markup_percentage' => 20,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('master-data.golongan-pelanggan'));

        $response
            ->assertOk()
            ->assertSee('Daftar golongan pelanggan')
            ->assertSee($group->name);
    }

    public function test_authenticated_user_can_view_customer_master_page(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::query()->create([
            'code' => 'GPL-0001',
            'name' => 'Klinik',
            'markup_percentage' => 20,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'code' => 'PLG-0001',
            'customer_group_id' => $group->id,
            'name' => 'Klinik Sehat',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('master-data.pelanggan'));

        $response
            ->assertOk()
            ->assertSee('Daftar pelanggan')
            ->assertSee($customer->name)
            ->assertSee($group->name);
    }
}
