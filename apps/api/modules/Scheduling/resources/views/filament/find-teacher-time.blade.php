{{--
    A time to the minute (Find a teacher): the hour in the reader's way (Clock),
    any minute, kept as HH:MM; the Alpine scope around it holds `h` and `m`.
--}}
@php
    use Modules\SharedKernel\Time\Clock;
    use Modules\SharedKernel\Time\TimeFormat;

    $twelve = Clock::format() !== TimeFormat::TwentyFour;
@endphp
<div class="ft-time" role="group" aria-label="{{ __('scheduling::find.exact.time') }}">
    <span class="ft-time-fields" dir="ltr">
        <select x-model.number="h" aria-label="{{ __('scheduling::find.exact.hour') }}">
            @foreach (range(0, 23) as $hour)
                <option value="{{ $hour }}">{{ $twelve ? Clock::hour($hour) : sprintf('%02d', $hour) }}</option>
            @endforeach
        </select>
        <span aria-hidden="true">:</span>
        <input type="number" min="0" max="59" step="1" inputmode="numeric" x-model.number="m" aria-label="{{ __('scheduling::find.exact.minute') }}"
            x-on:change="m = Math.min(59, Math.max(0, Math.trunc(Number(m) || 0)))">
    </span>
    <output class="ft-time-out" x-text="new Intl.DateTimeFormat(@js(app()->getLocale().'-u-nu-latn'), { hour: 'numeric', minute: '2-digit', hourCycle: @js(Clock::hourCycle()), timeZone: 'UTC' }).format(Date.UTC(2020, 0, 1, h, Math.min(59, Math.max(0, m | 0))))"></output>
</div>
