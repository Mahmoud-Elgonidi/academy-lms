@php
    // In the Platform tab: the Quran's text on the server, and fetching it again from Tanzil.
    $l = 'platform::settings.';
    $page = $getLivewire();
    $q = $page->quran;
@endphp
<div class="ps-quran">
    @if ($q['available'])
        <dl>
            <div><dt>{{ __($l.'quran.where') }}</dt><dd>{{ __($l.'quran.where_'.$q['where']) }}{{ $q['fetched_at'] ? ' · '.$q['fetched_at'] : '' }}</dd></div>
            <div><dt>{{ __($l.'quran.copy') }}</dt><dd dir="ltr">{{ $q['notice'] }}</dd></div>
            <div><dt>{{ __($l.'quran.verses') }}</dt><dd>{{ number_format((int) $q['verses']) }}</dd></div>
            @if ($q['text_sha256'])
                <div><dt>SHA-256</dt><dd dir="ltr" class="ps-hash">{{ $q['text_sha256'] }}</dd></div>
            @endif
        </dl>
    @else
        <p class="ps-missing">{{ __($l.'quran.missing') }}</p>
    @endif
    <div class="ps-quran-bar">{{ $page->fetchQuranAction }}</div>
</div>
