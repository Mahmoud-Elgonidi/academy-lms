{{-- A quick-add field: a title and Enter adds a $kind under $parent ("root": a level), and the field stays for the next one. --}}
@php $placeholder = __('curriculum::curriculum.builder.quick.'.$kind); @endphp
<div class="cb-quick" wire:key="quick-{{ $parent }}-{{ $kind }}">
    <label class="cb-quick-field">
        <span aria-hidden="true">+</span>
        <input type="text" wire:model="quick.{{ $parent }}" wire:keydown.enter.prevent="quickAdd('{{ $parent }}')"
            placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" maxlength="200" dir="auto" autocomplete="off">
        <kbd>{{ __('curriculum::curriculum.builder.quick.enter') }}</kbd>
    </label>
    {{ ($this->addChildAction)(['node' => $parent === 'root' ? null : $parent, 'kind' => $kind, 'details' => true]) }}
</div>
