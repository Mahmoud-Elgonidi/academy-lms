@php $l = 'scheduling::calendar.'; @endphp

<div class="cal-summary lms-stagger">
    @foreach (['teachers' => 'teacher', 'subjects' => 'subject'] as $group => $what)
        <section class="cal-card cal-box">
            <h3 class="lms-heading">{{ __($l.'summary.'.$group) }}</h3>
            @if ($board[$group] === [])
                <p class="cal-muted">{{ __($l.'summary.empty') }}</p>
            @else
                <div class="cal-table-wrap">
                    <table class="cal-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __($l.'summary.'.$what) }}</th>
                                @foreach ($board['columns'] as $c)
                                    <th scope="col"><span class="cal-dot" data-status="{{ $c['status'] }}"></span>{{ $c['label'] }}</th>
                                @endforeach
                                <th scope="col">{{ __($l.'summary.total') }}</th>
                                <th scope="col">{{ __($l.'summary.hours') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($board[$group] as $r)
                                <tr>
                                    <th scope="row"><button type="button" wire:click="focus('{{ $what }}', '{{ $r['id'] }}')">{{ $r['name'] }}</button></th>
                                    @foreach ($board['columns'] as $c)
                                        <td @if ($r['statuses'][$c['status']] === 0) data-zero @endif>{{ $r['statuses'][$c['status']] ?: '·' }}</td>
                                    @endforeach
                                    <td><b>{{ $r['total'] }}</b></td>
                                    <td>{{ $r['hours'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($group === 'teachers' && count($board['teachers']) > 1)
                            <tfoot>
                                <tr>
                                    <th scope="row">{{ __($l.'summary.all') }}</th>
                                    @foreach ($board['columns'] as $c)
                                        <td>{{ $board['total']['statuses'][$c['status']] }}</td>
                                    @endforeach
                                    <td><b>{{ $board['total']['total'] }}</b></td>
                                    <td>{{ $board['total']['hours'] }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </section>
    @endforeach
    <p class="cal-note">{{ __($l.'summary.hint') }}</p>
</div>
