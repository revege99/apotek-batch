<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            activeModal: null,
            openModal(id) {
                this.activeModal = id;
            },
            closeModal() {
                this.activeModal = null;
            },
        }"
        @keydown.escape.window="closeModal()"
        class="space-y-5"
    >
        <section class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-6 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">{{ number_format($stats['user_count']) }} user</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">{{ number_format($stats['section_count']) }} grup menu</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">{{ number_format($stats['menu_count']) }} submenu</span>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-4 py-2 text-[0.78rem] font-medium text-emerald-700">{{ number_format($stats['selected_count']) }} centang aktif</span>
                    </div>

                    <p class="text-[0.76rem] text-slate-500">Klik aksi pada user yang ingin diatur, centang menu yang dibutuhkan, lalu simpan dari modal.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="master-table min-w-full divide-y divide-slate-200/80 text-[0.8rem]">
                    <thead>
                        <tr class="text-left text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">
                            <th class="px-4 py-2.5">ID</th>
                            <th class="px-4 py-2.5">Nama User</th>
                            <th class="px-4 py-2.5">Username</th>
                            <th class="px-4 py-2.5">Role</th>
                            <th class="px-4 py-2.5">Ringkasan Akses</th>
                            <th class="table-action-head">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @foreach ($users as $user)
                            @php
                                $primaryRole = $user->roles->first();
                                $selectedCodes = collect($permissionMap[$user->id] ?? []);
                                $summaryItems = collect($matrix)
                                    ->flatMap(fn (array $group) => collect($group['children']))
                                    ->filter(fn (array $child) => $selectedCodes->contains($child['permission_code']))
                                    ->pluck('label')
                                    ->take(4);
                                $modalId = 'access-modal-'.$user->id;
                            @endphp
                            <tr class="align-middle">
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center font-medium text-slate-700">{{ $user->id }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center font-semibold text-slate-900">{{ $user->name }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center font-medium text-sky-700">{{ $user->username }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center">
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $primaryRole?->name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($summaryItems as $label)
                                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[0.68rem] font-medium text-slate-600">{{ $label }}</span>
                                            @empty
                                                <span class="text-[0.74rem] text-slate-400">Belum ada akses menu.</span>
                                            @endforelse
                                            @if ($selectedCodes->count() > 4)
                                                <span class="inline-flex rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[0.68rem] font-medium text-slate-500">+{{ $selectedCodes->count() - 4 }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button type="button" class="ui-action-btn ui-action-btn--soft px-4" @click="openModal(@js($modalId))">
                                        Atur hak akses
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @foreach ($users as $user)
            @php
                $primaryRole = $user->roles->first();
                $selectedCodes = collect($permissionMap[$user->id] ?? []);
                $modalId = 'access-modal-'.$user->id;
            @endphp
            <div
                x-cloak
                x-show="activeModal === @js($modalId)"
                x-transition.opacity
                class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
                @click.self="closeModal()"
            >
                <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                    <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-5xl overflow-y-auto p-6 sm:max-h-[calc(100vh-3rem)] sm:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-2">
                                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                    Atur Hak Akses
                                </div>
                                <div>
                                    <h3 class="section-title-lg">{{ $user->name }}</h3>
                                    <p class="mt-1 text-[0.78rem] text-slate-500">
                                        Username `{{ $user->username }}` • Role {{ $primaryRole?->name ?? '-' }}
                                    </p>
                                </div>
                            </div>

                            <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900" @click="closeModal()">
                                <span class="sr-only">Tutup modal</span>
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('pengaturan.hak-akses.update', $user) }}" class="mt-6 space-y-5">
                            @csrf
                            @method('PATCH')

                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ($matrix as $group)
                                    <section class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/70 p-4">
                                        <div class="space-y-1">
                                            <h4 class="text-sm font-semibold text-slate-900">{{ $group['label'] }}</h4>
                                            @if (($group['summary'] ?? '') !== '')
                                                <p class="text-[0.72rem] leading-5 text-slate-500">{{ $group['summary'] }}</p>
                                            @endif
                                        </div>

                                        <div class="mt-4 space-y-2.5">
                                            @foreach ($group['children'] as $child)
                                                @php
                                                    $isDisabled = ($child['superadmin_only'] ?? false) === true || $user->isSuperadmin();
                                                    $isChecked = $user->isSuperadmin()
                                                        || (($child['superadmin_only'] ?? false) === true && $user->isSuperadmin())
                                                        || $selectedCodes->contains($child['permission_code']);
                                                @endphp
                                                <label class="flex items-start gap-3 rounded-[1.15rem] border border-slate-200/80 bg-white px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $child['permission_code'] }}"
                                                        @checked($isChecked)
                                                        @disabled($isDisabled)
                                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
                                                    >
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-semibold text-slate-900">{{ $child['label'] }}</span>
                                                        <span class="mt-1 block text-[0.72rem] text-slate-500">{{ $child['route'] }}</span>
                                                        @if (($child['superadmin_only'] ?? false) === true)
                                                            <span class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.16em] text-amber-700">Superadmin only</span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>

                            <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-[0.76rem] text-slate-500">Centang semua menu yang boleh diakses user ini, lalu simpan setelah selesai mengatur.</p>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <button type="button" class="ui-action-btn ui-action-btn--neutral px-4" @click="closeModal()">Batal</button>
                                    <button type="submit" class="ui-action-btn ui-action-btn--soft px-4">Simpan hak akses</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
