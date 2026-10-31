@php $d = $this->hifz; @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <div class="lf-stack lms-stagger">
        <section class="lf-card">
            <div class="lf-head"><h3>{{ __('quran::hifz.title') }}</h3></div>
            @include('quran::filament.hifz.map', ['map' => $d['map']])
            <h4 style="font-size: .85rem; font-weight: 700; margin: .4rem 0 0">{{ __('quran::hifz.recent') }}</h4>
            @forelse ($d['recent'] as $r)
                <small class="lf-muted" style="display: block">{{ __('quran::hifz.page', ['page' => $r['page']]) }} · {{ $r['grade'] }} · {{ $r['source'] }} · {{ $r['at'] }}</small>
            @empty
                <small class="lf-muted">{{ __('quran::hifz.no_recent') }}</small>
            @endforelse
        </section>
        @include('quran::filament.mushaf.book', ['book' => $d['book'], 'tz' => $d['tz'], 'title' => __('quran::mushaf.book'), 'empty' => __('quran::mushaf.book_empty')])
    </div>
</x-filament-panels::page>
