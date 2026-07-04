<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display the supplier master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = Supplier::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('suppliers.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'editingSupplier' => $editId ? Supplier::query()->find($editId) : null,
            'stats' => [
                'total' => Supplier::count(),
                'active' => Supplier::where('is_active', true)->count(),
                'cities' => Supplier::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->count('city'),
            ],
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(SupplierRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Supplier::query()->create([
            'code' => $this->nextSupplierCode(),
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'city' => $validated['city'] ?: null,
            'payment_term_days' => $validated['payment_term_days'] ?? 0,
            'tax_number' => $validated['tax_number'] ?: null,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.supplier')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data supplier berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified supplier.
     */
    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validated();

        $supplier->update([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'city' => $validated['city'] ?: null,
            'payment_term_days' => $validated['payment_term_days'] ?? 0,
            'tax_number' => $validated['tax_number'] ?: null,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.supplier')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data supplier berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        try {
            $supplier->delete();
        } catch (QueryException) {
            return redirect()
                ->route('master-data.supplier')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Supplier tidak bisa dihapus karena sudah dipakai pada transaksi lain.',
                ]);
        }

        return redirect()
            ->route('master-data.supplier')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data supplier berhasil dihapus.',
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
        $page = collect($siblings)->firstWhere('route', 'master-data.supplier');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Generate the next supplier code.
     */
    private function nextSupplierCode(): string
    {
        $nextNumber = ((int) Supplier::max('id')) + 1;

        do {
            $candidate = sprintf('SUP-%04d', $nextNumber);
            $nextNumber++;
        } while (Supplier::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
