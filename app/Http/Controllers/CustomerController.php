<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display the customer master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $groupId = trim((string) $request->string('group_id', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = Customer::query()
            ->with('customerGroup:id,name,markup_percentage')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($groupId !== 'all' && ctype_digit($groupId), fn ($query) => $query->where('customer_group_id', (int) $groupId))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('customers.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'groupId' => ($groupId === 'all' || ctype_digit($groupId)) ? $groupId : 'all',
            'groupOptions' => $this->groupOptions(),
            'editingCustomer' => $editId ? Customer::query()->find($editId) : null,
            'stats' => [
                'total' => Customer::query()->count(),
                'active' => Customer::query()->where('is_active', true)->count(),
                'with_group' => Customer::query()->whereNotNull('customer_group_id')->count(),
                'groups' => CustomerGroup::query()->count(),
            ],
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(CustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Customer::query()->create([
            'code' => $this->nextCode(),
            'customer_group_id' => $validated['customer_group_id'] ?: null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.pelanggan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data pelanggan berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validated();

        $customer->update([
            'customer_group_id' => $validated['customer_group_id'] ?: null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.pelanggan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data pelanggan berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->sales()->exists()) {
            return redirect()
                ->route('master-data.pelanggan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Pelanggan tidak bisa dihapus karena sudah dipakai pada transaksi penjualan.',
                ]);
        }

        try {
            $customer->delete();
        } catch (QueryException) {
            return redirect()
                ->route('master-data.pelanggan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Pelanggan tidak bisa dihapus karena sudah dipakai pada transaksi penjualan.',
                ]);
        }

        return redirect()
            ->route('master-data.pelanggan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data pelanggan berhasil dihapus.',
            ]);
    }

    /**
     * Get page metadata.
     *
     * @return array<string, mixed>
     */
    private function pageData(): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Master Data');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', 'master-data.pelanggan');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Get available customer group options.
     */
    private function groupOptions()
    {
        return CustomerGroup::query()
            ->orderBy('name')
            ->get(['id', 'name', 'markup_percentage', 'is_active']);
    }

    /**
     * Generate the next customer code.
     */
    private function nextCode(): string
    {
        $nextNumber = ((int) Customer::query()->max('id')) + 1;

        do {
            $candidate = sprintf('PLG-%04d', $nextNumber);
            $nextNumber++;
        } while (Customer::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
