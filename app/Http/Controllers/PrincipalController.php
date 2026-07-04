<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrincipalRequest;
use App\Models\Principal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrincipalController extends Controller
{
    /**
     * Display the pharmaceutical-industry master page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status', 'all'));
        $editId = $request->integer('edit') ?: (int) $request->session()->getOldInput('_edit_id');

        $items = Principal::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('principals.index', [
            ...$this->pageData(),
            'items' => $items,
            'search' => $search,
            'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            'editingPrincipal' => $editId ? Principal::query()->find($editId) : null,
            'stats' => [
                'total' => Principal::count(),
                'active' => Principal::where('is_active', true)->count(),
                'cities' => Principal::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->count('city'),
            ],
        ]);
    }

    /**
     * Store a newly created pharmaceutical industry.
     */
    public function store(PrincipalRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Principal::query()->create([
            'code' => $this->nextPrincipalCode(),
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'city' => $validated['city'] ?: null,
            'address' => $validated['address'] ?: null,
            'email' => null,
            'notes' => null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('master-data.pabrik-principal')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data industri farmasi berhasil ditambahkan.',
            ]);
    }

    /**
     * Update the specified pharmaceutical industry.
     */
    public function update(PrincipalRequest $request, Principal $principal): RedirectResponse
    {
        $validated = $request->validated();

        $principal->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'city' => $validated['city'] ?: null,
            'address' => $validated['address'] ?: null,
            'email' => null,
            'notes' => null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('master-data.pabrik-principal')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data industri farmasi berhasil diperbarui.',
            ]);
    }

    /**
     * Remove the specified pharmaceutical industry.
     */
    public function destroy(Principal $principal): RedirectResponse
    {
        $principal->delete();

        return redirect()
            ->route('master-data.pabrik-principal')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data industri farmasi berhasil dihapus.',
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
        $page = collect($siblings)->firstWhere('route', 'master-data.pabrik-principal');

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Master Data',
            'siblings' => $siblings,
        ];
    }

    /**
     * Generate the next principal code.
     */
    private function nextPrincipalCode(): string
    {
        $nextNumber = ((int) Principal::max('id')) + 1;

        do {
            $candidate = sprintf('PRN-%04d', $nextNumber);
            $nextNumber++;
        } while (Principal::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
