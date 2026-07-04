<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorageLocationRequest;
use App\Models\StorageLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageLocationController extends Controller
{
    /**
     * Display the storage location master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = StorageLocation::query()
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

        return view('storage-locations.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'editingLocation' => $editId ? StorageLocation::query()->find($editId) : null,
            'stats' => [
                'total' => StorageLocation::count(),
                'active' => StorageLocation::where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created storage location.
     */
    public function store(StorageLocationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        StorageLocation::query()->create([
            'code' => $this->nextLocationCode(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.lokasi-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data lokasi penyimpanan berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified storage location.
     */
    public function update(StorageLocationRequest $request, StorageLocation $storageLocation): RedirectResponse
    {
        $validated = $request->validated();

        $storageLocation->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.lokasi-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data lokasi penyimpanan berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified storage location.
     */
    public function destroy(StorageLocation $storageLocation): RedirectResponse
    {
        $storageLocation->delete();

        return redirect()
            ->route('master-data.lokasi-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data lokasi penyimpanan berhasil dihapus.',
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
        $page = collect($siblings)->firstWhere('route', 'master-data.lokasi-obat');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Generate the next location code.
     */
    private function nextLocationCode(): string
    {
        $nextNumber = 1;

        do {
            $candidate = sprintf('LOC-%04d', $nextNumber);
            $nextNumber++;
        } while (StorageLocation::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
