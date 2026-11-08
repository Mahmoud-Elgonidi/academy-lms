@php $l = 'platform::engines.'; $engines = $this->engines; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::console.ai-styles')

    @if ($engines === [])
        <section class="pc-base pc-box ai-empty">
            <h3>{{ __($l.'empty.title') }}</h3>
            <p>{{ __($l.'empty.body') }}</p>
        </section>
    @endif

    <div class="pc-grid lms-stagger">
        @foreach ($engines as $e)
            <article class="pc-base pc-card ai-engine" wire:key="engine-{{ $e['id'] }}" @if (! $e['enabled']) data-off @endif>
                <header>
                    <div>
                        <h3>{{ $e['name'] }}</h3>
                        <small>{{ $e['kind'] }} · {{ implode(' · ', $e['does']) }}</small>
                    </div>
                    <span class="ai-chip" data-status="{{ $e['enabled'] ? $e['status'] : 'off' }}">{{ $e['enabled'] ? __('platform::health.status.'.$e['status']) : __($l.'card.disabled') }}</span>
                </header>
                <p class="ai-health">{{ $e['health'] }}@if ($e['hint'])<em>{{ $e['hint'] }}</em>@endif</p>
                <dl class="pc-dl">
                    <dt>{{ __($l.'card.host') }}</dt><dd><span>{{ $e['host'] }}</span></dd>
                    <dt>{{ __($l.'card.key') }}</dt><dd>{{ $e['key'] ? __($l.'card.key_kept') : __($l.'card.no_key') }}</dd>
                    @foreach ($e['models'] as $m)
                        <dt>{{ $loop->first ? __($l.'card.models') : '' }}</dt><dd><span>{{ $m }}</span></dd>
                    @endforeach
                    @if ($e['timeout'])<dt>{{ __($l.'card.timeout') }}</dt><dd>{{ __($l.'card.seconds', ['n' => $e['timeout']]) }}</dd>@endif
                    @if ($e['cost'])<dt>{{ __($l.'card.cost') }}</dt><dd><span>{{ $e['cost'] }}</span></dd>@endif
                    <dt>{{ __($l.'card.routes') }}</dt><dd>{{ trans_choice($l.'card.in_routes', $e['routes'], ['count' => $e['routes']]) }}</dd>
                </dl>
                @if ($e['notes'])<p class="ai-notes">{{ $e['notes'] }}</p>@endif
                <footer class="pc-foot">
                    <span>{{ __($l.'card.updated', ['when' => $e['updated']]) }}</span>
                    <span class="ai-actions">{{ ($this->tryAction)(['engine' => $e['id']]) }} {{ ($this->editAction)(['engine' => $e['id']]) }}</span>
                </footer>
            </article>
        @endforeach
    </div>
</x-filament-panels::page>
