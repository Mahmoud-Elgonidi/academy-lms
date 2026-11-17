{{-- A block's background, drawn in the academy's own colours. viewData: tones {value: label}. --}}
@php $statePath = $getStatePath(); @endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="lms-pick" role="radiogroup" style="grid-template-columns: repeat(auto-fill, minmax(5rem, 1fr))">
        @foreach ($tones as $value => $label)
            @php $id = str_replace('.', '-', $statePath).'-'.$value; @endphp
            <label for="{{ $id }}" wire:key="{{ $id }}">
                <input type="radio" id="{{ $id }}" name="{{ $statePath }}" value="{{ $value }}" wire:model.live="{{ $statePath }}">
                <span class="lms-swatch" data-tone="{{ $value }}" aria-hidden="true">Aa</span>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
