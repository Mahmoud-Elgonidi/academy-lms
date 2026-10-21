@php
    $l = 'crm::leads.';
    $d = $this->details;
    $trials = $this->trials;
    $steps = ['new', 'contacted', 'trial_booked', 'attended', 'assessed', 'won'];
    $icons = ['call' => 'heroicon-m-phone', 'whatsapp' => 'heroicon-m-chat-bubble-left-ellipsis', 'note' => 'heroicon-m-pencil', 'created' => 'heroicon-m-plus',
        'assigned' => 'heroicon-m-user', 'stage' => 'heroicon-m-arrow-long-left', 'won' => 'heroicon-m-check', 'lost' => 'heroicon-m-x-mark',
        'reopened' => 'heroicon-m-arrow-uturn-left', 'edited' => 'heroicon-m-pencil-square', 'website_again' => 'heroicon-m-globe-alt', 'assessed' => 'heroicon-m-clipboard-document-check'];
@endphp
<x-filament-panels::page>
    @include('crm::filament.styles')

    <div class="crm-steps" aria-label="{{ $d['stageLabel'] }}">
        @foreach ($steps as $i => $step)
            <span class="crm-step" data-on="{{ $d['stage'] === 'lost' ? 'off' : ($i < $d['step'] ? 'done' : ($i === $d['step'] ? 'now' : 'off')) }}">{{ __($l.'stages.'.$step) }}</span>
        @endforeach
    </div>

    @if ($d['stage'] === 'won')
        <div class="crm-banner" data-kind="won">
            <x-filament::icon icon="heroicon-o-check-badge" style="width: 1.25rem; height: 1.25rem" />
            <span>{{ __($l.'page.won_note', ['date' => $d['changed']]) }}</span>
            @if ($d['enrollmentUrl'])<a href="{{ $d['enrollmentUrl'] }}" wire:navigate>{{ __($l.'page.enrollment') }}</a>@endif
        </div>
    @elseif ($d['stage'] === 'lost')
        <div class="crm-banner" data-kind="lost">
            <x-filament::icon icon="heroicon-o-x-circle" style="width: 1.25rem; height: 1.25rem" />
            <span>{{ __($l.'page.lost_note', ['reason' => $d['lostReason'] ?? '']) }} · {{ $d['changed'] }}</span>
        </div>
    @endif

    <div class="crm-lead">
        <aside class="crm-card-base crm-box">
            <div class="crm-facts">
                @if ($d['phone'])
                <div>
                    <small>{{ __($l.'fields.phone_e164') }}</small>
                    <span class="crm-phone" dir="ltr">{{ $d['phone'] }}</span>
                </div>
                <div class="crm-contact-actions">
                    <x-filament::button tag="a" :href="$d['whatsapp']" target="_blank" rel="noopener" size="sm" icon="heroicon-m-chat-bubble-left-ellipsis">{{ __($l.'page.whatsapp') }}</x-filament::button>
                    <x-filament::button tag="a" :href="'tel:'.$d['phone']" size="sm" color="gray" icon="heroicon-m-phone">{{ __($l.'page.call') }}</x-filament::button>
                </div>
                @endif
                @if ($d['email'])<div><small>{{ __($l.'fields.email') }}</small><span dir="ltr">{{ $d['email'] }}</span></div>@endif
                <div>
                    <small>{{ collect([$d['country'], $d['zone']])->filter()->implode(' · ') }}</small>
                    <span>{{ __($l.'page.local_time', ['time' => $d['localTime']]) }}</span>
                </div>
                @if ($d['learner'])
                    <div><small>{{ __($l.'fields.learner_name') }}</small><span>{{ $d['learner'] }}@if ($d['age']) · {{ __($l.'card.age', ['years' => $d['age']]) }}@endif</span></div>
                @endif
                <div><small>{{ __($l.'sections.wants') }}</small><span>{{ $d['wants'] }}</span></div>
                <div><small>{{ __($l.'fields.source') }}</small><span>{{ $d['source'] }}@if ($d['utm']) · <span dir="ltr">{{ $d['utm'] }}</span>@endif</span></div>
                <div><small>{{ __($l.'fields.owner_id') }}</small><span>{{ $d['owner'] ?? __($l.'page.no_owner') }}</span></div>
                @if ($d['notes'])<div><small>{{ __($l.'page.notes') }}</small><span style="white-space: pre-line">{{ $d['notes'] }}</span></div>@endif
            </div>
        </aside>

        <div style="display: flex; flex-direction: column; gap: 1rem; min-width: 0">
            <section class="crm-card-base crm-box">
                <h3 class="lms-heading">{{ __($l.'page.trials') }}</h3>
                @forelse ($trials as $t)
                    <article class="crm-trial" wire:key="trial-{{ $t['id'] }}">
                        <header>
                            <strong>{{ __($l.'page.their_time', ['time' => $t['their']]) }}</strong>
                            <span class="crm-pill" data-s="{{ $t['status'] }}">{{ $t['statusLabel'] }}</span>
                        </header>
                        <small>{{ __($l.'page.with', ['teacher' => $t['teacher']]) }} · {{ __($l.'page.teacher_time', ['time' => $t['teacherAt']]) }}</small>
                        @if ($t['assessed'])
                            <div class="crm-assess">
                                <div><small>{{ __($l.'page.level') }}</small><strong>{{ $t['level'] }}</strong></div>
                                @if ($t['plan'])<div><small>{{ __($l.'page.plan') }}</small><strong>{{ $t['plan'] }}</strong></div>@endif
                                @if ($t['perWeek'])<div><small>&nbsp;</small><strong>{{ trans_choice($l.'page.per_week', $t['perWeek'], ['count' => $t['perWeek']]) }}</strong></div>@endif
                                @if ($t['assessment'])<p>{{ $t['assessment'] }}</p>@endif
                            </div>
                        @elseif ($t['status'] === 'attended')
                            <small>{{ __($l.'page.waiting') }}</small>
                        @endif
                    </article>
                @empty
                    <small style="color: var(--gray-500)">{{ __($l.'page.no_trials') }}</small>
                @endforelse
            </section>

            <section class="crm-card-base crm-box">
                <h3 class="lms-heading">{{ __($l.'page.timeline') }}</h3>
                <form class="crm-log" wire:submit="log">
                    <textarea wire:model="body" maxlength="2000" placeholder="{{ __($l.'page.body') }}" aria-label="{{ __($l.'page.body') }}"></textarea>
                    <div class="crm-log-row">
                        <div class="crm-seg" role="group">
                            @foreach (['call', 'whatsapp', 'note'] as $kind)
                                <button type="button" wire:click="$set('kind', '{{ $kind }}')" aria-pressed="{{ $this->kind === $kind ? 'true' : 'false' }}">{{ __($l.'kinds.'.$kind) }}</button>
                            @endforeach
                        </div>
                        <x-filament::button type="submit" size="sm" icon="heroicon-m-plus">{{ __($l.'page.log') }}</x-filament::button>
                    </div>
                </form>
                <div class="crm-timeline lms-stagger">
                    @foreach ($this->timeline as $event)
                        <div class="crm-event" data-kind="{{ $event['kind'] }}" wire:key="ev-{{ $event['id'] }}">
                            <span class="crm-event-icon"><x-filament::icon :icon="$icons[$event['kind']] ?? 'heroicon-m-sparkles'" /></span>
                            <div>
                                <strong>{{ $event['text'] }}</strong>
                                @if ($event['body'])<p>{{ $event['body'] }}</p>@endif
                                <small>{{ $event['who'] }} · <time title="{{ $event['when'] }}">{{ $event['ago'] }}</time></small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
