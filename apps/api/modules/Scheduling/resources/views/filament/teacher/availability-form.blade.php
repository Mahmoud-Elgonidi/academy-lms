{{-- The proposal form: the week, its first day and a note; send it, keep it as a draft, or close it. --}}
@php $l = 'scheduling::teacher.availability.'; @endphp
<form wire:submit="send" class="ta-form">
    <div>
        <h3 class="ta-form-title">{{ __($l.'propose') }}</h3>
        <p class="sp-muted">{{ __($l.'propose_hint') }}</p>
    </div>
    {{ $this->form }}
    <div class="ta-actions">
        <x-filament::button type="submit" icon="heroicon-o-paper-airplane">{{ __($l.'send') }}</x-filament::button>
        <x-filament::button color="gray" wire:click="saveDraft">{{ __($l.'save_draft') }}</x-filament::button>
        <x-filament::link tag="button" color="gray" wire:click="stopEditing">{{ __($l.'cancel') }}</x-filament::link>
    </div>
</form>
