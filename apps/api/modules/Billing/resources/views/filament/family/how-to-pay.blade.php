{{-- The proof form's first word: where to send the money for this invoice (the academy's words for the payer's region). --}}
@if ($note)
    @include('billing::filament.family.transfer', ['text' => $note])
@endif
