{{-- Which branding colour carries the page's buttons. viewData: accents {value: hex}, names {value: label}. --}}
@php $statePath = $getStatePath(); @endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="lms-pick" role="radiogroup" style="grid-template-columns: repeat(3, minmax(0, 1fr))">
        @foreach ($accents as $value => $hex)
            @php
                $id = str_replace('.', '-', $statePath).'-'.$value;
                $hex = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $hex) === 1 ? $hex : '#64748b';
            @endphp
            <label for="{{ $id }}" wire:key="{{ $id }}">
                <input type="radio" id="{{ $id }}" name="{{ $statePath }}" value="{{ $value }}" wire:model.live="{{ $statePath }}">
                <span class="lms-swatch" style="background: {{ $hex }}; color: white" aria-hidden="true">Aa</span>
                <span>{{ $names[$value] }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
