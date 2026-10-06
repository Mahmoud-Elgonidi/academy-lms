{{--
    The top of the side menu: hello by the time of day in the viewer's zone
    (ViewerTimezone), with the first name (past a title such as "أ."), and
    today's date. Folds away with the menu on desktop.
--}}
@php
    $user = auth()->user();
    $now = \Carbon\CarbonImmutable::now(app(\Modules\SharedKernel\Time\ViewerTimezone::class)->effective())->locale(app()->getLocale());
    $hour = (int) $now->format('G');
    $sky = match (true) {
        $hour >= 5 && $hour < 12 => 'morning',
        $hour >= 12 && $hour < 17 => 'day',
        $hour >= 17 && $hour < 20 => 'evening',
        default => 'night',
    };
    $first = collect(preg_split('/\s+/u', trim((string) $user?->name)))->first(fn (string $word): bool => $word !== '' && ! str_ends_with($word, '.')) ?? '';
@endphp
@if ($user)
    <div class="lms-hello" data-sky="{{ $sky }}" x-show="$store.sidebar.isOpen">
        <span class="lms-hello-sky" aria-hidden="true">
            {{ \Filament\Support\generate_icon_html($sky === 'night' ? \Filament\Support\Icons\Heroicon::OutlinedMoon : \Filament\Support\Icons\Heroicon::OutlinedSun) }}
        </span>
        <span class="lms-hello-text">
            <strong>{{ preg_replace('/[\s،,]+$/u', '', __('shared::chrome.hello.'.$sky, ['name' => $first])) }}</strong>
            <small>{{ $now->translatedFormat(__('shared::chrome.today')) }}</small>
        </span>
    </div>
@endif
