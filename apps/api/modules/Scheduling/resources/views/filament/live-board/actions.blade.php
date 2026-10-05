{{-- An item's actions: the first $buttons as buttons (the recommended one filled), the rest in a "more" menu. --}}
@php
    $buttons = $buttons ?? 2;
    $shown = array_slice($item['actions'], 0, $buttons);
    $rest = array_slice($item['actions'], $buttons);
@endphp
<div class="lb-actions">
    @foreach ($shown as $i => $action)
        {{ ($this->{$action['name'].'Action'})($action['args'] + ['primary' => $i === 0 && ! ($action['danger'] ?? false)]) }}
    @endforeach
    @if ($rest !== [])
        {{ $this->moreActions($rest) }}
    @endif
</div>
