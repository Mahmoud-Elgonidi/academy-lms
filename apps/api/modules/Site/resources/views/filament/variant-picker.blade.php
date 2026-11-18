{{-- A block's design, as small drawings (Forms\Schematics). viewData: choices {value: {label, svg}}. --}}
@php $statePath = $getStatePath(); @endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="lms-pick" role="radiogroup">
        @foreach ($choices as $value => $choice)
            @php $id = str_replace('.', '-', $statePath).'-'.$value; @endphp
            <label for="{{ $id }}" wire:key="{{ $id }}">
                <input type="radio" id="{{ $id }}" name="{{ $statePath }}" value="{{ $value }}" wire:model.live="{{ $statePath }}">
                {!! $choice['svg'] !!}
                <span>{{ $choice['label'] }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
