@php
    $user = Auth::user();
    $navigation = \App\Support\NavigationAccess::navigationFor($user);
    $operationalLabels = ['Master Data', 'Pembelian', 'Penjualan', 'Stok & Batch', 'Keuangan'];
    $initials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))
        ->implode('');

    $sections = [
        [
            'label' => 'Utama',
            'items' => [
                [
                    'kind' => 'link',
                    'label' => 'Dashboard',
                    'route' => 'dashboard',
                    'icon' => 'dashboard',
                    'badge' => 'Baru',
                ],
            ],
        ],
        [
            'label' => 'Operasional',
            'items' => $navigation
                ->filter(fn (array $item): bool => in_array($item['label'], $operationalLabels, true))
                ->map(fn (array $item): array => ['kind' => 'group'] + $item)
                ->values()
                ->all(),
        ],
        [
            'label' => 'Laporan',
            'items' => $navigation
                ->filter(fn (array $item): bool => $item['label'] === 'Laporan')
                ->map(fn (array $item): array => ['kind' => 'group'] + $item)
                ->values()
                ->all(),
        ],
        [
            'label' => 'Setup Saldo Awal',
            'items' => $navigation
                ->filter(fn (array $item): bool => $item['label'] === 'Setup Saldo Awal')
                ->map(fn (array $item): array => ['kind' => 'group'] + $item)
                ->values()
                ->all(),
        ],
        [
            'label' => 'Pengaturan',
            'items' => $navigation
                ->filter(fn (array $item): bool => $item['label'] === 'Pengaturan')
                ->map(fn (array $item): array => ['kind' => 'group'] + $item)
                ->values()
                ->all(),
        ],
    ];
    $isChildRouteActive = fn (array $child): bool => request()->routeIs($child['route']) || request()->routeIs($child['route'].'.*');
@endphp

<nav x-ref="sidebarNav" class="apotik-sidebar" @click.capture="rememberSidebarScroll(); handleSidebarNavigation($event)">
    <div class="apotik-sidebar__brand">
        <a href="{{ route('dashboard') }}" class="apotik-sidebar__brand-inner">
            <span class="apotik-sidebar__brand-icon">
                <x-application-logo class="h-5 w-5" />
            </span>

            <span class="min-w-0">
                <span class="apotik-sidebar__brand-label">Manajemen Apotik</span>
                <span class="apotik-sidebar__brand-name">{{ config('apotik.brand.name') }}</span>
            </span>
        </a>
    </div>

    <div x-ref="sidebarScroll" @scroll.passive.debounce.120ms="rememberSidebarScroll()" class="apotik-sidebar__scroll">
        @foreach ($sections as $sectionIndex => $section)
            @if (count($section['items']) > 0)
                <div>
                    <div class="apotik-sidebar__section-label">{{ $section['label'] }}</div>

                    <div class="apotik-sidebar__stack">
                        @foreach ($section['items'] as $item)
                            @if ($item['kind'] === 'link')
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class([
                                        'apotik-sidebar__item',
                                        'is-active' => request()->routeIs($item['route']),
                                    ])
                                >
                                    <span
                                        @class([
                                            'apotik-sidebar__icon',
                                            'is-active' => request()->routeIs($item['route']),
                                        ])
                                    >
                                        <x-sidebar-icon :name="$item['icon']" class="h-4 w-4" />
                                    </span>

                                    <span
                                        @class([
                                            'apotik-sidebar__text',
                                            'is-active' => request()->routeIs($item['route']),
                                        ])
                                    >
                                        {{ $item['label'] }}
                                    </span>

                                    @if (! empty($item['badge']))
                                        <span class="apotik-sidebar__badge">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @elseif (! empty($item['children']))
                                @php
                                    $isOpen = collect($item['children'])->contains(fn (array $child): bool => $isChildRouteActive($child));
                                @endphp

                                <div x-data="sidebarGroup(@js(\Illuminate\Support\Str::slug($section['label'].'-'.$item['label'])), @js($isOpen))" :class="{ 'is-open': open }" class="apotik-sidebar__group">
                                    <button type="button" class="apotik-sidebar__group-header" @click="toggle()">
                                        <span class="apotik-sidebar__icon">
                                            <x-sidebar-icon :name="$item['icon']" class="h-4 w-4" />
                                        </span>

                                        <span class="apotik-sidebar__text">{{ $item['label'] }}</span>

                                        <svg
                                            class="apotik-sidebar__chevron"
                                            :class="{ 'is-open': open }"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M7.22 4.97a.75.75 0 0 1 1.06 0l4.25 4.28a.75.75 0 0 1 0 1.06l-4.25 4.28a.75.75 0 0 1-1.06-1.06L10.94 10 7.22 6.03a.75.75 0 0 1 0-1.06Z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>

                                    <div
                                        x-show="open"
                                        x-transition.opacity.duration.150ms
                                        @style(['display: none;' => ! $isOpen])
                                        class="apotik-sidebar__children"
                                    >
                                        @foreach ($item['children'] as $child)
                                            <a
                                                href="{{ route($child['route']) }}"
                                                @class([
                                                    'apotik-sidebar__child',
                                                    'is-active' => $isChildRouteActive($child),
                                                ])
                                            >
                                                <span
                                                    @class([
                                                        'apotik-sidebar__child-dot',
                                                        'is-active' => $isChildRouteActive($child),
                                                    ])
                                                ></span>
                                                <span
                                                    @class([
                                                        'apotik-sidebar__child-text',
                                                        'is-active' => $isChildRouteActive($child),
                                                    ])
                                                >
                                                    {{ $child['label'] }}
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($sectionIndex < count($sections) - 1)
                <div class="apotik-sidebar__divider"></div>
            @endif
        @endforeach
    </div>

    <div class="apotik-sidebar__user-area">
        <div class="apotik-sidebar__user-card">
            <div class="apotik-sidebar__avatar">{{ $initials }}</div>

            <div class="apotik-sidebar__user-info">
                <div class="apotik-sidebar__user-name">{{ $user->name }}</div>
                <div class="apotik-sidebar__user-meta">{{ $user->email }}</div>
            </div>
        </div>

        <div class="apotik-sidebar__user-actions">
            <a href="{{ route('profile.edit') }}" class="apotik-sidebar__user-btn">
                <x-sidebar-icon name="profile" class="h-3.5 w-3.5" />
                <span>Profil</span>
            </a>

            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf

                <button type="submit" class="apotik-sidebar__user-btn apotik-sidebar__user-btn--logout">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.75 8.25V6.5A1.75 1.75 0 0 1 12.5 4.75h4A1.75 1.75 0 0 1 18.25 6.5v11A1.75 1.75 0 0 1 16.5 19.25h-4a1.75 1.75 0 0 1-1.75-1.75v-1.75" />
                        <path d="M14 12H5.75" />
                        <path d="m8.75 9-3 3 3 3" />
                    </svg>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </div>
</nav>
