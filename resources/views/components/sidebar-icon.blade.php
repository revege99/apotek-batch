@props(['name'])

@switch($name)
    @case('dashboard')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M4.75 5.75h5.5v5.5h-5.5z" />
            <path d="M13.75 5.75h5.5v8.5h-5.5z" />
            <path d="M4.75 14.25h5.5v4h-5.5z" />
            <path d="M13.75 17.25h5.5v1h-5.5z" />
        </svg>
        @break

    @case('archive')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M4.75 7.25h14.5l-1 11a1.5 1.5 0 0 1-1.49 1.36H7.24a1.5 1.5 0 0 1-1.49-1.36z" />
            <path d="M7 7.25V5.75A1.75 1.75 0 0 1 8.75 4h6.5A1.75 1.75 0 0 1 17 5.75v1.5" />
            <path d="M9.5 11.25h5" />
        </svg>
        @break

    @case('cart')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M3.75 5.25h2l1.7 8.1a1.5 1.5 0 0 0 1.47 1.2h6.98a1.5 1.5 0 0 0 1.46-1.14l1.39-5.91H7.1" />
            <path d="M9.25 18.5a.75.75 0 1 1 0 1.5a.75.75 0 0 1 0-1.5Z" />
            <path d="M16.75 18.5a.75.75 0 1 1 0 1.5a.75.75 0 0 1 0-1.5Z" />
        </svg>
        @break

    @case('receipt')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M7 4.75h10v14.5l-2-1.25-2 1.25-2-1.25-2 1.25-2-1.25z" />
            <path d="M9.5 9h5" />
            <path d="M9.5 12h5" />
            <path d="M9.5 15h3.25" />
        </svg>
        @break

    @case('boxes')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="m12 4 7 4-7 4-7-4 7-4Z" />
            <path d="m5 8 7 4 7-4" />
            <path d="M5 8v8l7 4 7-4V8" />
            <path d="M12 12v8" />
        </svg>
        @break

    @case('wallet')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M4.75 8.25A2.25 2.25 0 0 1 7 6h10a2.25 2.25 0 0 1 2.25 2.25v7.5A2.25 2.25 0 0 1 17 18H7a2.25 2.25 0 0 1-2.25-2.25z" />
            <path d="M4.75 9.25h14.5" />
            <path d="M15.75 13h2.5" />
        </svg>
        @break

    @case('document')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M8 4.75h6l4 4v10.5A1.75 1.75 0 0 1 16.25 21h-8.5A1.75 1.75 0 0 1 6 19.25V6.5A1.75 1.75 0 0 1 7.75 4.75z" />
            <path d="M14 4.75v4h4" />
            <path d="M9 12h6" />
            <path d="M9 15.25h6" />
        </svg>
        @break

    @case('cog')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M12 8.25a3.75 3.75 0 1 0 0 7.5a3.75 3.75 0 0 0 0-7.5Z" />
            <path d="M19.5 12a7.64 7.64 0 0 0-.08-1l1.46-1.13-1.5-2.6-1.77.51a7.76 7.76 0 0 0-1.72-1L15.5 4h-3l-.39 1.78a7.76 7.76 0 0 0-1.72 1l-1.77-.51-1.5 2.6L8.58 11a7.64 7.64 0 0 0 0 2l-1.46 1.13 1.5 2.6 1.77-.51a7.76 7.76 0 0 0 1.72 1L12.5 20h3l.39-1.78a7.76 7.76 0 0 0 1.72-1l1.77.51 1.5-2.6L19.42 13c.05-.33.08-.66.08-1Z" />
        </svg>
        @break

    @case('profile')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <path d="M12 12a3.75 3.75 0 1 0 0-7.5a3.75 3.75 0 0 0 0 7.5Z" />
            <path d="M5.75 19.25a6.25 6.25 0 0 1 12.5 0" />
        </svg>
        @break

    @default
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
            <circle cx="12" cy="12" r="7.25" />
        </svg>
@endswitch
