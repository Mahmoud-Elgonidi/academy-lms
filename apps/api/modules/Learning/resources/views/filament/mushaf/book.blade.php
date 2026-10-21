{{--
    A learner's mistakes notebook (Queries\Mushaf\MistakesBook): by surah and verse, each verse as the platform's copy
    writes it with the words their teachers marked. $book, $title, $empty; $kids leaves out the counts and the dates; $tz.
--}}
@php
    $m = 'learning::mushaf.';
    $kids ??= false;
    $digits = fn (int $n) => strtr((string) $n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    $first = fn (array $kinds) => collect(['wrong', 'hesitation', 'tajweed'])->first(fn ($k) => in_array($k, $kinds, true)) ?? 'wrong';
@endphp
<style>
    @font-face { font-family: 'Amiri Quran'; src: url('{{ asset('fonts/quran/AmiriQuran-Regular.ttf') }}') format('truetype'); font-display: swap; }
    .lm-surah { display: flex; flex-direction: column; gap: .35rem; padding-top: .5rem; border-top: 1px solid var(--gray-200); }
    .lm-surah h4 { margin: 0; font-size: .9rem; font-weight: 700; }
    .lm-verse { margin: 0; font-family: 'Amiri Quran', 'Amiri', serif; font-size: 1.35rem; line-height: 2.3; }
    .lm-verse mark { border-radius: .35rem; padding: 0 .1rem; background: var(--danger-100); color: var(--danger-800); }
    .lm-verse mark[data-kind="hesitation"] { background: var(--warning-100); color: var(--warning-800); }
    .lm-verse mark[data-kind="tajweed"] { background: var(--info-100); color: var(--info-800); }
    .lm-end { color: var(--primary-600); font-size: .8em; }
    .lm-words { display: flex; flex-wrap: wrap; gap: .25rem .75rem; }
    .dark .lm-surah { border-color: var(--gray-700); }
    .dark .lm-verse mark { background: var(--danger-900); color: var(--danger-100); }
    .dark .lm-verse mark[data-kind="hesitation"] { background: var(--warning-900); color: var(--warning-100); }
    .dark .lm-verse mark[data-kind="tajweed"] { background: var(--info-900); color: var(--info-100); }
</style>
<section class="lf-card" id="mistakes-book">
    <div class="lf-head">
        <h3>{{ $title }}</h3>
        @if (! $kids && $book['words'] > 0)
            <span class="lf-pill">{{ trans_choice($m.'book_words', $book['words'], ['count' => $book['words']]) }}</span>
        @endif
    </div>
    @forelse ($book['surahs'] as $s)
        <div class="lm-surah">
            <h4>{{ app()->getLocale() === 'ar' || $s['name_en'] === '' ? $s['name_ar'] : $s['name_en'] }}@unless ($kids) · {{ trans_choice($m.'book_words', $s['words'], ['count' => $s['words']]) }}@endunless</h4>
            @foreach ($s['verses'] as $v)
                @php $marks = collect($v['marks'])->keyBy('word'); @endphp
                <p class="lm-verse" dir="rtl" lang="ar">
                    @foreach ($v['words'] as $i => $word)
                        @if ($mark = $marks[$i + 1] ?? null)
                            <mark data-kind="{{ $first($mark['kinds']) }}" title="{{ collect($mark['kinds'])->map(fn ($k) => __($m.'kinds.'.$k))->join('، ') }}">{{ $word }}</mark>
                        @else
                            {{ $word }}
                        @endif
                    @endforeach
                    <span class="lm-end">﴿{{ $digits($v['verse']) }}﴾</span>
                </p>
                @unless ($kids)
                    <div class="lm-words">
                        @foreach ($v['marks'] as $mark)
                            <small class="lf-muted">
                                <span dir="rtl" lang="ar">{{ $v['words'][$mark['word'] - 1] ?? '' }}</span>
                                · {{ collect($mark['kinds'])->map(fn ($k) => __($m.'kinds.'.$k))->join('، ') }}
                                · {{ trans_choice($m.'times', $mark['times'], ['count' => $mark['times']]) }}
                                · {{ __($m.'last', ['when' => $mark['last_at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M')]) }}
                            </small>
                        @endforeach
                    </div>
                @endunless
            @endforeach
        </div>
    @empty
        <div class="lf-empty">{{ $empty }}</div>
    @endforelse
</section>
