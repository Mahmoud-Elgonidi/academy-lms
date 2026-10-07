{{-- "My settings": each time the platform team signed in as this person, by whom, when, for how long and why. --}}
<div class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
    @foreach ($rows as $row)
        <div class="flex flex-col gap-0.5 py-2.5 first:pt-0 last:pb-0">
            <span class="text-sm font-semibold">{{ __('identity::impersonation.mine.row', ['admin' => $row['admin'], 'when' => $row['when'], 'minutes' => $row['minutes']]) }}</span>
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $row['reason'] }}</span>
        </div>
    @endforeach
</div>
