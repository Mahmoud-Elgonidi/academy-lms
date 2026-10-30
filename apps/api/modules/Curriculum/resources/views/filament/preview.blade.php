{{--
    The program as a student's journey draws it, with no learner: each level an island of the world
    in turn (shared::games.island, as the journey's map), its lessons the stations on its path.
    $islands: list of {title, art: ?CatalogIsland, stations: list<string>}.
--}}
@include('shared::games.art-styles')
<style>
    .cp { display: flex; flex-direction: column; gap: .9rem; }
    .cp-island { display: flex; gap: 1rem; align-items: flex-start; padding: .9rem; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); background: var(--gray-50); }
    .cp-art { position: relative; flex: none; width: 6.5rem; height: 5.5rem; overflow: hidden; border-radius: 1.1rem; background: color-mix(in oklab, var(--info-200, var(--primary-200)) 55%, white); }
    .cp-art > span { position: absolute; left: 50%; top: 50%; width: 200px; height: 190px; transform: translate(-50%, -52%) scale(.5); transform-origin: 50% 50%; }
    .cp-art > b { position: absolute; inset: 0; display: grid; place-items: center; font-size: 1.6rem; font-weight: 800; color: var(--primary-700); }
    .cp-body { flex: 1; min-width: 0; }
    .cp-body small { color: var(--gray-500); font-size: .75rem; }
    .cp-body h3 { font-size: 1rem; font-weight: 700; margin-bottom: .4rem; }
    .cp-path { position: relative; display: flex; flex-direction: column; gap: .3rem; margin: 0; padding: 0; list-style: none; }
    .cp-path::before { content: ''; position: absolute; inset-block: .8rem; inset-inline-start: .85rem; width: 3px; border-radius: 3px; background: var(--gray-200); }
    .cp-path li { position: relative; display: flex; align-items: center; gap: .6rem; font-size: .85rem; }
    .cp-path li > span { position: relative; z-index: 1; flex: none; display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 999px; border: 2px solid var(--primary-400); background: white; color: var(--primary-700); font-size: .72rem; font-weight: 800; }
    .cp-path li:first-child > span { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .cp-none { color: var(--gray-500); font-size: .8rem; }
    .dark .cp-island { background: var(--gray-800); border-color: var(--gray-700); }
    .dark .cp-path::before { background: var(--gray-700); }
    .dark .cp-path li > span { background: var(--gray-900); color: var(--primary-300); }
</style>
<div class="cp lms-stagger">
    @foreach ($islands as $i => $island)
        <section class="cp-island lms-lift" aria-label="{{ $island['title'] }}">
            <div class="cp-art" aria-hidden="true">
                @if ($island['art'])
                    <span>@include('shared::games.island', ['key' => $island['art']->key, 'locked' => false, 'colors' => $island['art']->colors, 'emoji' => $island['art']->emoji])</span>
                @else
                    <b>{{ $i + 1 }}</b>
                @endif
            </div>
            <div class="cp-body">
                <small>{{ __('curriculum::curriculum.preview.island', ['n' => $i + 1, 'of' => count($islands)]) }}</small>
                <h3>{{ $island['title'] }}</h3>
                @if ($island['stations'] === [])
                    <p class="cp-none">{{ __('curriculum::curriculum.preview.no_stations') }}</p>
                @else
                    <ol class="cp-path">
                        @foreach ($island['stations'] as $k => $station)
                            <li><span>{{ $k + 1 }}</span>{{ $station }}</li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    @endforeach
</div>
