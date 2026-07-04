<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorageRackRequest;
use App\Models\StorageLocation;
use App\Models\StorageRack;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageRackController extends Controller
{
    /**
     * Display the storage rack master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $locationId = $request->integer('storage_location_id') ?: null;
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = StorageRack::query()
            ->with('location:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($locationId !== null, fn ($query) => $query->where('storage_location_id', $locationId))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('storage-racks.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'locationId' => $locationId,
            'locations' => StorageLocation::query()->active()->orderBy('name')->get(['id', 'name']),
            'editingRack' => $editId ? StorageRack::query()->find($editId) : null,
            'stats' => [
                'total' => StorageRack::count(),
                'active' => StorageRack::where('is_active', true)->count(),
                'linked_locations' => StorageRack::query()->whereNotNull('storage_location_id')->distinct('storage_location_id')->count('storage_location_id'),
            ],
        ]);
    }

    /**
     * Store a newly created storage rack.
     */
    public function store(StorageRackRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        StorageRack::query()->create([
            'code' => $this->nextRackCode(),
            'name' => $validated['name'],
            'storage_location_id' => filled($validated['storage_location_id'] ?? null) ? (int) $validated['storage_location_id'] : null,
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.rak-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data rak obat berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified storage rack.
     */
    public function update(StorageRackRequest $request, StorageRack $storageRack): RedirectResponse
    {
        $validated = $request->validated();

        $storageRack->update([
            'name' => $validated['name'],
            'storage_location_id' => filled($validated['storage_location_id'] ?? null) ? (int) $validated['storage_location_id'] : null,
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.rak-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data rak obat berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified storage rack.
     */
    public function destroy(StorageRack $storageRack): RedirectResponse
    {
        $storageRack->delete();

        return redirect()
            ->route('master-data.rak-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data rak obat berhasil dihapus.',
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
        $page = collect($siblings)->firstWhere('route', 'master-data.rak-obat');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Generate the next rack code.
     */
    private function nextRackCode(): string
    {
        $nextNumber = 1;

        do {
            $candidate = sprintf('RAK-%04d', $nextNumber);
            $nextNumber++;
        } while (StorageRack::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
