<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerGroupRequest;
use App\Models\CustomerGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    /**
     * Display the customer group master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = CustomerGroup::query()
            ->withCount('customers')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('customer-groups.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'editingCustomerGroup' => $editId ? CustomerGroup::query()->find($editId) : null,
            'stats' => [
                'total' => CustomerGroup::query()->count(),
                'active' => CustomerGroup::query()->where('is_active', true)->count(),
                'customers' => CustomerGroup::query()->withCount('customers')->get()->sum('customers_count'),
                'highest_markup' => (float) CustomerGroup::query()->max('markup_percentage'),
            ],
        ]);
    }

    /**
     * Store a newly created customer group.
     */
    public function store(CustomerGroupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        CustomerGroup::query()->create([
            'code' => $this->nextCode(),
            'name' => $validated['name'],
            'markup_percentage' => round((float) $validated['markup_percentage'], 2),
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.golongan-pelanggan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Golongan pelanggan berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified customer group.
     */
    public function update(CustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $validated = $request->validated();

        $customerGroup->update([
            'name' => $validated['name'],
            'markup_percentage' => round((float) $validated['markup_percentage'], 2),
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.golongan-pelanggan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Golongan pelanggan berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified customer group.
     */
    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        if ($customerGroup->customers()->exists() || $customerGroup->sales()->exists()) {
            return redirect()
                ->route('master-data.golongan-pelanggan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Golongan pelanggan tidak bisa dihapus karena masih dipakai pada pelanggan atau transaksi penjualan.',
                ]);
        }

        try {
            $customerGroup->delete();
        } catch (QueryException) {
            return redirect()
                ->route('master-data.golongan-pelanggan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Golongan pelanggan tidak bisa dihapus karena sudah dipakai pada data lain.',
                ]);
        }

        return redirect()
            ->route('master-data.golongan-pelanggan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Golongan pelanggan berhasil dihapus.',
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
        $page = collect($siblings)->firstWhere('route', 'master-data.golongan-pelanggan');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Generate the next customer group code.
     */
    private function nextCode(): string
    {
        $nextNumber = ((int) CustomerGroup::query()->max('id')) + 1;

        do {
            $candidate = sprintf('GPL-%04d', $nextNumber);
            $nextNumber++;
        } while (CustomerGroup::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
