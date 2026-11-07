{{--
    The back office's menu groups (SharedKernel\Filament\NavGroup) each show
    their icon beside the name. Filament takes either a group's icon or its
    items' (it drops the items' when a group has one), and the items keep
    theirs as chips, so the group's is drawn here as a mask of its heroicon,
    in the label's own colour.
--}}
@php
    $css = static fn (string $text): string => str_replace(['\\', '"', '<'], ['\\\\', '\\"', '\\3c '], $text);
    $svg = static function (\Filament\Support\Icons\Heroicon $icon): ?string {
        $file = base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg/'.$icon->value.'.svg');

        return is_file($file) ? 'data:image/svg+xml,'.rawurlencode(preg_replace('/\s+/', ' ', trim((string) file_get_contents($file)))) : null;
    };
@endphp
<style>
    .fi-sidebar-group:is(@foreach (\Modules\SharedKernel\Filament\NavGroup::cases() as $group)[data-group-label="{!! $css($group->label()) !!}"]@if (! $loop->last), @endif @endforeach) > .fi-sidebar-group-btn .fi-sidebar-group-label::before {
        content: ''; flex: none; width: 1rem; height: 1rem; background: currentColor;
        -webkit-mask: var(--lms-group-icon) center / contain no-repeat; mask: var(--lms-group-icon) center / contain no-repeat;
    }
    @foreach (\Modules\SharedKernel\Filament\NavGroup::cases() as $group)
        @if ($uri = $svg($group->icon()))
            .fi-sidebar-group[data-group-label="{!! $css($group->label()) !!}"] { --lms-group-icon: url("{!! $uri !!}"); }
        @endif
    @endforeach
</style>
