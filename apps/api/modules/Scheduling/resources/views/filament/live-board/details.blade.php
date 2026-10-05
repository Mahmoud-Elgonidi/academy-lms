{{-- What a coordinator needs to decide about one session: the three clocks, people, log. --}}
<div class="lb-detail-grid">
    <div>
        <h3 class="lb-h3">{{ __('scheduling::board.detail.times') }}</h3>
        <div class="lb-clocks">
            @foreach ($item['clocks'] as $clock)
                <div class="lb-clock">
                    <small>{{ $clock['who'] }}</small>
                    <strong class="lb-num lms-heading">{{ $clock['time'] }}</strong>
                    <small>{{ $clock['city'] }}</small>
                </div>
            @endforeach
        </div>
    </div>
    <div>
        <h3 class="lb-h3">{{ __('scheduling::board.detail.log') }}</h3>
        <ol class="lb-log">
            @foreach ($item['log'] as $entry)
                <li><span class="lb-num">{{ $entry['at'] }}</span>{{ $entry['text'] }}</li>
            @endforeach
        </ol>
    </div>
    <div>
        <h3 class="lb-h3">{{ __('scheduling::board.detail.people') }}</h3>
        <dl class="lb-dl">
            @foreach ($item['guardians'] as $guardian)
                <dt>{{ $guardian['relationship'] }}</dt>
                <dd>{{ $guardian['name'] }}@if ($guardian['phone']) · <a href="tel:{{ $guardian['phone'] }}" dir="ltr">{{ $guardian['phone'] }}</a>@endif</dd>
            @endforeach
            <dt>{{ __('scheduling::board.detail.funding') }}</dt>
            <dd>{{ $item['funding'] }}</dd>
            @if ($item['typeLabel'])
                <dt>{{ __('scheduling::board.detail.type') }}</dt>
                <dd>{{ $item['typeLabel'] }}</dd>
            @endif
        </dl>
    </div>
</div>
