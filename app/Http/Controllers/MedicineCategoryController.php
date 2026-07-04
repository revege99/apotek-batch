<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicineCategoryRequest;
use App\Models\MedicineCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MedicineCategoryController extends Controller
{
    /**
     * Display the medicine category master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $classificationType = trim((string) $request->string('classification_type', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = MedicineCategory::query()
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
            ->when(
                in_array($classificationType, $this->typeOptions()->keys()->all(), true),
                fn ($query) => $query->forClassificationType($classificationType)
            )
            ->orderBy('classification_type')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('medicine-categories.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'classificationType' => in_array($classificationType, array_merge(['all'], $this->typeOptions()->keys()->all()), true) ? $classificationType : 'all',
            'typeOptions' => $this->typeOptions(),
            'editingCategory' => $editId ? MedicineCategory::query()->find($editId) : null,
            'stats' => [
                'total' => MedicineCategory::count(),
                'active' => MedicineCategory::where('is_active', true)->count(),
                'types' => MedicineCategory::forClassificationType(MedicineCategory::TYPE_MEDICINE_TYPE)->count(),
                'categories' => MedicineCategory::forClassificationType(MedicineCategory::TYPE_CATEGORY)->count(),
                'groups' => MedicineCategory::forClassificationType(MedicineCategory::TYPE_GROUP)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created category lookup.
     */
    public function store(MedicineCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MedicineCategory::query()->create([
            'code' => $this->nextCode($validated['classification_type']),
            'classification_type' => $validated['classification_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.kategori-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Master kategori obat berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified category lookup.
     */
    public function update(MedicineCategoryRequest $request, MedicineCategory $medicineCategory): RedirectResponse
    {
        $validated = $request->validated();

        $medicineCategory->update([
            'classification_type' => $validated['classification_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.kategori-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Master kategori obat berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified category lookup.
     */
    public function destroy(MedicineCategory $medicineCategory): RedirectResponse
    {
        $medicineCategory->delete();

        return redirect()
            ->route('master-data.kategori-obat')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Master kategori obat berhasil dihapus.',
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
        $page = collect($siblings)->firstWhere('route', 'master-data.kategori-obat');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Get the available classification types.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function typeOptions(): Collection
    {
        return collect([
            MedicineCategory::TYPE_MEDICINE_TYPE => 'Jenis Obat',
            MedicineCategory::TYPE_CATEGORY => 'Kategori Obat',
            MedicineCategory::TYPE_GROUP => 'Golongan Obat',
        ]);
    }

    /**
     * Generate the next code for the given type.
     */
    private function nextCode(string $classificationType): string
    {
        $prefixes = [
            MedicineCategory::TYPE_MEDICINE_TYPE => 'JNS',
            MedicineCategory::TYPE_CATEGORY => 'KTG',
            MedicineCategory::TYPE_GROUP => 'GLG',
        ];

        $prefix = $prefixes[$classificationType] ?? 'KAT';
        $nextNumber = MedicineCategory::query()
            ->forClassificationType($classificationType)
            ->pluck('code')
            ->map(function (?string $code): int {
                if (! is_string($code) || ! preg_match('/(\d+)$/', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() + 1;

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextNumber);
            $nextNumber++;
        } while (MedicineCategory::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
