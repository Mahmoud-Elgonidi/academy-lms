{{-- My settings: the email and mobile number (the ways in), each with the way to change it after a code to the new one. --}}
@php /** @var list<array{kind: string, label: string, value: ?string}> $rows */ @endphp
<ul class="lms-contact">
    @foreach ($rows as $row)
        <li data-kind="{{ $row['kind'] }}">
            <div class="lms-contact-what">
                <strong>{{ $row['label'] }}</strong>
                <span dir="ltr">{{ $row['value'] ?? __('identity::settings.contact.none') }}</span>
            </div>
            @unless ($managed)
                {{ ($this->changeContactAction)(['kind' => $row['kind'], 'has' => filled($row['value'])]) }}
            @endunless
        </li>
    @endforeach
</ul>
@if ($managed)
    <p class="lms-contact-note">{{ __('identity::settings.contact.managed') }}</p>
@endif
<style>
    .lms-contact { display: grid; gap: 0.75rem; }
    .lms-contact li { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
    .lms-contact-what { display: grid; min-width: 0; }
    .lms-contact-what span { color: var(--gray-500); font-size: 0.875rem; overflow-wrap: anywhere; text-align: start; }
    .lms-contact-note { margin: .75rem 0 0; color: var(--gray-500); font-size: .875rem; }
</style>
