@php
    $toast = session('toast');
@endphp

@if (is_array($toast) && filled($toast['message'] ?? null))
    @php
        $type = $toast['type'] ?? 'success';
        $duration = $type === 'error' ? 7000 : 3600;
        $styles = match ($type) {
            'error' => [
                'ring' => 'border-rose-200 bg-white text-rose-950',
                'badge' => 'border-rose-100 bg-rose-50 text-rose-700',
                'icon' => 'text-rose-600',
                'bar' => 'bg-rose-500',
            ],
            default => [
                'ring' => 'border-emerald-200 bg-white text-emerald-950',
                'badge' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                'icon' => 'text-emerald-600',
                'bar' => 'bg-emerald-500',
            ],
        };
    @endphp

    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, {{ $duration }})"
        x-show="show"
        x-transition:enter="ease-out duration-250"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-[1.5rem] border shadow-[0_24px_60px_-24px_rgba(15,23,42,0.35)] {{ $styles['ring'] }}"
    >
        <div class="flex items-start gap-3 px-4 py-4">
            <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl {{ $styles['badge'] }}">
                @if ($type === 'error')
                    <svg class="h-5 w-5 {{ $styles['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 8v5" />
                        <path d="M12 16h.01" />
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                @else
                    <svg class="h-5 w-5 {{ $styles['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 12.75 11.25 15 15 9.75" />
                        <circle cx="12" cy="12" r="8" />
                    </svg>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-950">
                    {{ $type === 'error' ? 'Tindakan gagal' : 'Berhasil' }}
                </p>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    {{ $toast['message'] }}
                </p>
            </div>

            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-400 transition hover:border-slate-300 hover:text-slate-700"
                @click="show = false"
            >
                <span class="sr-only">Tutup notifikasi</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <div class="h-1 w-full bg-slate-100">
            <div class="h-full w-full origin-left {{ $styles['bar'] }}" style="animation: shrink {{ number_format($duration / 1000, 1, '.', '') }}s linear forwards;"></div>
        </div>
    </div>
@endif
