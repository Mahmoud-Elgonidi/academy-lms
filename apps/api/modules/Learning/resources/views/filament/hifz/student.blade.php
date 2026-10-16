@php $d = $this->hifz; @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <div class="lf-stack lms-stagger">
        <section class="lf-card">
            <div class="lf-head"><h3>{{ __('learning::hifz.title') }}</h3></div>
            @include('learning::filament.hifz.map', ['map' => $d['map']])
            <h4 style="font-size: .85rem; font-weight: 700; margin: .4rem 0 0">{{ __('learning::hifz.recent') }}</h4>
            @forelse ($d['recent'] as $r)
                <small class="lf-muted" style="display: block">{{ __('learning::hifz.page', ['page' => $r['page']]) }} · {{ $r['grade'] }} · {{ $r['source'] }} · {{ $r['at'] }}</small>
            @empty
                <small class="lf-muted">{{ __('learning::hifz.no_recent') }}</small>
            @endforelse
        </section>
        @include('learning::filament.mushaf.book', ['book' => $d['book'], 'tz' => $d['tz'], 'title' => __('learning::mushaf.book'), 'empty' => __('learning::mushaf.book_empty')])
    </div>
</x-filament-panels::page>
