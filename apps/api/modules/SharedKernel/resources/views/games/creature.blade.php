@php
    /*
     * A creature, drawn (the games' design): its kind and colours from the
     * catalog's look. $size the width in px; $shadow true draws the locked
     * outline only; $motion flaps wings and blinks.
     */
    $look = (array) ($look ?? []);
    $kind = $look['kind'] ?? 'bird';
    $shadow = (bool) ($shadow ?? false);
    $motion = (bool) ($motion ?? false);
    $size = (int) ($size ?? 84);
    $L = $line ?? '#13294B';
    $sh = '#D7CCF5';
    $c = fn (string $k, string $d) => $shadow ? $sh : (is_string($look[$k] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $look[$k]) ? $look[$k] : $d);
    $body = $c('body', '#74C0FC'); $wing = $c('wing', '#4DABF7'); $belly = $c('belly', '#E7F5FF');
    $tuft = $c('tuft', '#FF6B6B'); $beak = $c('beak', '#FF922B');
    $crest = ! $shadow && isset($look['crest']) ? $c('crest', '#FF922B') : null;
    $st = $shadow ? 'none' : $L;
    $wl = $motion ? 'cr-wl' : ''; $wr = $motion ? 'cr-wr' : ''; $blink = $motion ? 'cr-blink' : '';
    $eyes = function (float $x1, float $x2, float $y, float $r = 7) use ($L, $blink, $shadow) {
        if ($shadow) return '';
        return '<g class="'.$blink.'"><circle cx="'.$x1.'" cy="'.$y.'" r="'.$r.'" fill="'.$L.'"/><circle cx="'.$x2.'" cy="'.$y.'" r="'.$r.'" fill="'.$L.'"/></g>'
            .'<circle cx="'.($x1 - 2).'" cy="'.($y - 2.5).'" r="'.($r / 3).'" fill="#FFFFFF"/><circle cx="'.($x2 - 2).'" cy="'.($y - 2.5).'" r="'.($r / 3).'" fill="#FFFFFF"/>';
    };
    $cheeks = fn (float $x1, float $x2, float $y) => $shadow ? '' : '<ellipse cx="'.$x1.'" cy="'.$y.'" rx="5" ry="3.2" fill="#FFA8C5"/><ellipse cx="'.$x2.'" cy="'.$y.'" rx="5" ry="3.2" fill="#FFA8C5"/>';
    $smile = fn (float $x, float $y, float $w = 6) => $shadow ? '' : '<path d="M'.($x - $w).' '.$y.' Q'.$x.' '.($y + $w * .8).' '.($x + $w).' '.$y.'" fill="none" stroke="'.$L.'" stroke-width="3" stroke-linecap="round"/>';
@endphp
<svg class="cr {{ $class ?? '' }}" width="{{ $size }}" height="{{ round($size * 105 / 120) }}" viewBox="0 0 120 105" style="overflow: visible" aria-hidden="true">
@switch($kind)
    @case('turtle')
        <ellipse cx="30" cy="86" rx="11" ry="7" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="3.5"/><ellipse cx="90" cy="86" rx="11" ry="7" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="3.5"/>
        <circle cx="60" cy="30" r="18" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <path d="M16 82 C16 52 36 40 60 40 C84 40 104 52 104 82 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/>
        <path d="M28 78 C28 58 42 48 60 48 C78 48 92 58 92 78 Z" fill="{{ $belly }}"/>
        <path d="M50 58 L60 52 L70 58 L70 70 L60 76 L50 70 Z" fill="{{ $wing }}" opacity=".7"/>
        <rect x="12" y="78" width="96" height="10" rx="5" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3.5"/>
        {!! $eyes(53, 67, 28, 4.6) !!}{!! $smile(60, 36, 4) !!}
        @break
    @case('crab')
        <g transform="translate(14 22) scale(1.32)">
            <path d="M24 14 V6 M42 14 V6" stroke="{{ $st }}" stroke-width="2.5"/>
            @unless ($shadow)<circle cx="24" cy="6" r="4.5" fill="#FFFFFF" stroke="{{ $L }}" stroke-width="2"/><circle cx="42" cy="6" r="4.5" fill="#FFFFFF" stroke="{{ $L }}" stroke-width="2"/><circle cx="24" cy="6" r="2" fill="{{ $L }}"/><circle cx="42" cy="6" r="2" fill="{{ $L }}"/>@endunless
            <path d="M14 36 L6 44 M20 38 L16 48 M46 38 L50 48 M52 36 L60 44" stroke="{{ $shadow ? $sh : $wing }}" stroke-width="3" stroke-linecap="round"/>
            <ellipse cx="33" cy="28" rx="22" ry="14" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="2.5"/>
            {!! $shadow ? '' : '<path d="M27 32 Q33 36 39 32" fill="none" stroke="'.$L.'" stroke-width="2" stroke-linecap="round"/>' !!}
            <g class="{{ $motion ? 'cr-claw' : '' }}"><path d="M56 22 C66 14 70 4 62 0 C60 6 56 8 52 6 C50 12 52 18 56 22 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="2.5" stroke-linejoin="round"/></g>
            <path d="M10 22 C2 16 0 8 6 4 C8 10 12 12 14 10 C16 14 14 20 10 22 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="2.5" stroke-linejoin="round"/>
        </g>
        @break
    @case('octopus')
        <path d="M28 58 C28 72 18 86 24 96 C32 90 36 82 40 76 C40 86 38 96 46 102 C52 94 52 86 54 80 C56 88 58 98 66 100 C70 92 66 84 68 80 C72 86 78 96 88 96 C90 86 84 76 82 70 C86 76 94 84 98 82 C98 72 92 62 92 56 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/>
        <ellipse cx="60" cy="44" rx="34" ry="32" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        @unless ($shadow)<circle cx="44" cy="24" r="4" fill="{{ $wing }}"/><circle cx="76" cy="28" r="3" fill="{{ $wing }}"/><circle cx="66" cy="18" r="2.5" fill="{{ $wing }}"/>@endunless
        {!! $eyes(48, 72, 46, 7) !!}{!! $cheeks(38, 82, 58) !!}{!! $smile(60, 60, 6) !!}
        @break
    @case('whale')
        <path d="M100 54 C108 40 116 34 118 40 C116 46 112 52 108 56 C114 58 118 62 116 68 C110 66 104 62 100 60 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/>
        <path d="M8 62 C8 36 34 24 64 26 C92 28 108 44 106 60 C104 80 80 92 52 92 C26 92 8 82 8 62 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <path d="M16 72 C32 86 72 90 98 70 C82 84 40 88 16 72 Z" fill="{{ $belly }}"/>
        @unless ($shadow)<path class="{{ $motion ? 'cr-spout' : '' }}" d="M50 22 C46 12 42 8 36 6 M56 22 C58 12 62 8 68 6 M53 20 V4" fill="none" stroke="#74C0FC" stroke-width="3" stroke-linecap="round"/>@endunless
        {!! $eyes(30, 52, 54, 5.5) !!}{!! $cheeks(24, 60, 66) !!}{!! $smile(41, 66, 5) !!}
        @break
    @case('monkey')
        <path d="M84 70 C104 72 106 52 94 48" fill="none" stroke="{{ $shadow ? $sh : $wing }}" stroke-width="6" stroke-linecap="round"/>
        <ellipse cx="60" cy="74" rx="22" ry="20" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <circle cx="30" cy="40" r="10" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/><circle cx="90" cy="40" r="10" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <circle cx="60" cy="42" r="26" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="50" rx="18" ry="14" fill="{{ $belly }}"/>
        {!! $eyes(51, 69, 40, 4.5) !!}{!! $smile(60, 54, 5) !!}
        @break
    @case('sloth')
        <path d="M30 76 C20 84 18 94 26 98 M90 76 C100 84 102 94 94 98" fill="none" stroke="{{ $shadow ? $sh : $wing }}" stroke-width="7" stroke-linecap="round"/>
        <path d="M22 62 C22 32 40 18 60 18 C80 18 98 32 98 62 C98 88 80 100 60 100 C40 100 22 88 22 62 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="50" rx="28" ry="21" fill="{{ $belly }}"/>
        @unless ($shadow)<ellipse cx="46" cy="49" rx="10" ry="6" fill="{{ $wing }}" transform="rotate(-20 46 49)"/><ellipse cx="74" cy="49" rx="10" ry="6" fill="{{ $wing }}" transform="rotate(20 74 49)"/>
        <path d="M42 49 Q46 52 50 49 M70 49 Q74 52 78 49" stroke="#FFFFFF" stroke-width="2.5" fill="none" stroke-linecap="round"/><ellipse cx="60" cy="58" rx="5" ry="3.5" fill="{{ $L }}"/>@endunless
        {!! $smile(60, 64, 6) !!}
        @break
    @case('elephant')
        <circle cx="24" cy="50" r="22" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/><circle cx="96" cy="50" r="22" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        @unless ($shadow)<circle cx="24" cy="50" r="13" fill="{{ $wing }}"/><circle cx="96" cy="50" r="13" fill="{{ $wing }}"/>@endunless
        <circle cx="60" cy="48" r="31" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <path d="M52 64 C50 82 52 94 64 98 C72 100 74 92 68 90 C62 88 62 80 66 66" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/>
        {!! $eyes(48, 72, 44, 5) !!}{!! $cheeks(40, 80, 58) !!}
        @break
    @case('tiger')
        <circle cx="30" cy="26" r="11" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/><circle cx="90" cy="26" r="11" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="56" rx="40" ry="36" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="70" rx="20" ry="15" fill="{{ $belly }}"/>
        @unless ($shadow)<path d="M60 22 V34 M50 24 L54 34 M70 24 L66 34 M22 52 L34 54 M22 62 L32 62 M98 52 L86 54 M98 62 L88 62" stroke="{{ $wing }}" stroke-width="4" stroke-linecap="round"/>
        <path d="M55 64 L65 64 L60 70 Z" fill="{{ $L }}"/>@endunless
        {!! $eyes(46, 74, 50, 6) !!}{!! $smile(60, 74, 5) !!}
        @break
    @case('penguin')
        <path d="M24 60 C10 66 8 78 16 82 C22 76 26 70 30 66 Z M96 60 C110 66 112 78 104 82 C98 76 94 70 90 66 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/>
        <ellipse cx="60" cy="58" rx="34" ry="40" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="66" rx="23" ry="29" fill="{{ $belly }}"/>
        @unless ($shadow)<path d="M44 98 L40 104 L52 102 Z M76 98 L80 104 L68 102 Z" fill="{{ $beak }}"/>@endunless
        {!! $eyes(49, 71, 40, 5) !!}{!! $cheeks(42, 78, 52) !!}
        @unless ($shadow)<path d="M54 48 L66 48 L60 56 Z" fill="{{ $beak }}" stroke="{{ $L }}" stroke-width="2.5" stroke-linejoin="round"/>@endunless
        @break
    @case('seal')
        <path d="M14 80 C14 58 34 46 56 48 C78 50 100 60 106 80 C98 92 30 94 14 80 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <path d="M98 66 C108 56 116 58 116 64 C112 68 106 72 102 74 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3"/>
        <circle cx="40" cy="44" r="24" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="40" cy="54" rx="12" ry="8" fill="{{ $belly }}"/>
        @unless ($shadow)<ellipse cx="40" cy="50" rx="4" ry="3" fill="{{ $L }}"/><path d="M28 54 H18 M28 58 L18 62 M52 54 H62 M52 58 L62 62" stroke="{{ $L }}" stroke-width="2" stroke-linecap="round"/>@endunless
        {!! $eyes(32, 48, 40, 4.5) !!}
        @break
    @case('bear')
        <circle cx="30" cy="28" r="13" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/><circle cx="90" cy="28" r="13" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        @unless ($shadow)<circle cx="30" cy="28" r="6" fill="{{ $wing }}"/><circle cx="90" cy="28" r="6" fill="{{ $wing }}"/>@endunless
        <circle cx="60" cy="58" r="38" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="70" rx="17" ry="13" fill="{{ $belly }}" stroke="{{ $st }}" stroke-width="2.5"/>
        @unless ($shadow)<ellipse cx="60" cy="64" rx="6" ry="4.5" fill="{{ $L }}"/>@endunless
        {!! $eyes(46, 74, 50, 5) !!}{!! $cheeks(36, 84, 66) !!}{!! $smile(60, 74, 4) !!}
        @break
    @case('fox')
        <path d="M84 84 C106 86 116 66 108 58 C102 70 92 74 82 74 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/>
        <path d="M24 44 L30 8 L52 32 Z M96 44 L90 8 L68 32 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/>
        @unless ($shadow)<path d="M30 36 L33 18 L44 32 Z M90 36 L87 18 L76 32 Z" fill="{{ $wing }}"/>@endunless
        <path d="M18 46 C18 72 38 92 60 94 C82 92 102 72 102 46 C92 34 76 30 60 30 C44 30 28 34 18 46 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/>
        <path d="M22 54 C32 70 46 78 60 80 C74 78 88 70 98 54 C88 64 74 70 60 70 C46 70 32 64 22 54 Z" fill="{{ $belly }}"/>
        @unless ($shadow)<ellipse cx="60" cy="72" rx="5" ry="4" fill="{{ $L }}"/>@endunless
        {!! $eyes(46, 74, 54, 5.5) !!}
        @break
    @case('lizard')
        <path d="M84 76 C104 80 116 66 106 54 C98 48 90 56 96 62" fill="none" stroke="{{ $st }}" stroke-width="13" stroke-linecap="round"/>
        <path d="M84 76 C104 80 116 66 106 54 C98 48 90 56 96 62" fill="none" stroke="{{ $body }}" stroke-width="7" stroke-linecap="round"/>
        <path d="M44 84 L38 96 M70 84 L76 96" stroke="{{ $st }}" stroke-width="5" stroke-linecap="round"/>
        <ellipse cx="60" cy="74" rx="32" ry="16" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="36" cy="50" rx="24" ry="19" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        @unless ($shadow)<circle cx="62" cy="66" r="4" fill="{{ $wing }}"/><circle cx="76" cy="72" r="3" fill="{{ $wing }}"/><circle cx="52" cy="76" r="3" fill="{{ $wing }}"/>@endunless
        {!! $eyes(28, 46, 42, 6) !!}{!! $smile(36, 56, 6) !!}
        @break
    @case('chick')
        @unless ($shadow)<path d="M48 26 C44 12 54 6 58 14 C60 2 72 6 66 20 C76 14 80 24 70 30 Z" fill="{{ $tuft }}" stroke="{{ $L }}" stroke-width="3" stroke-linejoin="round"/>@endunless
        <circle cx="60" cy="58" r="34" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <g class="{{ $wl }}"><ellipse cx="26" cy="62" rx="9" ry="14" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3"/></g>
        <g class="{{ $wr }}"><ellipse cx="94" cy="62" rx="9" ry="14" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3"/></g>
        <path d="M28 80 L36 74 L44 82 L52 74 L60 82 L68 74 L76 82 L84 74 L92 80 C92 96 78 102 60 102 C42 102 28 96 28 80 Z" fill="{{ $shadow ? $sh : '#F3E3C3' }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/>
        {!! $eyes(48, 72, 50, 6) !!}{!! $cheeks(38, 82, 62) !!}
        @unless ($shadow)<path d="M53 58 L67 58 L60 67 Z" fill="{{ $beak }}" stroke="{{ $L }}" stroke-width="2.5" stroke-linejoin="round"/>@endunless
        @break
    @case('dino')
        @unless ($shadow)<path d="M58 16 L64 4 L70 16 Z M74 16 L82 6 L84 20 Z M88 22 L98 14 L96 28 Z" fill="{{ $wing }}" stroke="{{ $L }}" stroke-width="2.5" stroke-linejoin="round"/>@endunless
        <path d="M44 70 C40 88 46 102 62 102 C78 102 86 88 82 70 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <path d="M18 50 C18 26 38 14 62 14 C88 14 104 30 104 50 C104 66 92 76 76 76 L40 76 C26 76 18 64 18 50 Z" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <path d="M50 84 C50 94 70 94 72 84 Z" fill="{{ $belly }}"/>
        @unless ($shadow)<path d="M34 64 L40 70 L46 64 L52 70 L58 64 L64 70 L70 64" fill="#FFFFFF" stroke="{{ $L }}" stroke-width="2" stroke-linejoin="round"/><circle cx="96" cy="40" r="2.5" fill="{{ $L }}"/>
        <path d="M44 84 L34 88" stroke="{{ $L }}" stroke-width="4" stroke-linecap="round"/>@endunless
        {!! $eyes(46, 70, 38, 6) !!}
        @break
    @case('dragon')
        <g class="{{ $wl }}"><path d="M34 46 C16 20 0 26 4 44 C8 58 22 62 36 58 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/></g>
        <g class="{{ $wr }}"><path d="M86 46 C104 20 120 26 116 44 C112 58 98 62 84 58 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/></g>
        <path d="M84 84 C100 92 108 86 106 78" fill="none" stroke="{{ $st }}" stroke-width="9" stroke-linecap="round"/>
        <path d="M84 84 C100 92 108 86 106 78" fill="none" stroke="{{ $body }}" stroke-width="4" stroke-linecap="round"/>
        @unless ($shadow)<path class="{{ $motion ? 'cr-flame' : '' }}" d="M106 78 C102 68 108 62 112 66 C114 60 120 64 116 72 C114 78 110 80 106 78 Z" fill="{{ $tuft }}"/>@endunless
        <ellipse cx="60" cy="60" rx="33" ry="31" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="72" rx="19" ry="14" fill="{{ $belly }}"/>
        @unless ($shadow)<path d="M44 32 L38 16 L52 28 Z M76 32 L82 16 L68 28 Z" fill="{{ $tuft }}" stroke="{{ $L }}" stroke-width="2.5" stroke-linejoin="round"/>
        <circle cx="56" cy="66" r="1.8" fill="{{ $L }}"/><circle cx="64" cy="66" r="1.8" fill="{{ $L }}"/>@endunless
        {!! $eyes(48, 72, 52, 6.5) !!}{!! $cheeks(38, 82, 64) !!}
        @break
    @default
        @if ($crest)<path d="M48 22 L44 2 L54 16 L60 0 L66 16 L76 2 L72 22" fill="{{ $crest }}" stroke="{{ $L }}" stroke-width="3" stroke-linejoin="round"/>@endif
        <g class="{{ $wl }}"><path d="M32 48 C14 30 -2 36 2 50 C6 62 20 64 34 60 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/></g>
        <g class="{{ $wr }}"><path d="M88 48 C106 30 122 36 118 50 C114 62 100 64 86 60 Z" fill="{{ $wing }}" stroke="{{ $st }}" stroke-width="4" stroke-linejoin="round"/></g>
        <ellipse cx="60" cy="56" rx="34" ry="31" fill="{{ $body }}" stroke="{{ $st }}" stroke-width="4"/>
        <ellipse cx="60" cy="69" rx="20" ry="14" fill="{{ $belly }}"/>
        <path d="M54 26 C50 14 58 12 60 20 C62 12 70 14 66 26" fill="{{ $tuft }}" stroke="{{ $st }}" stroke-width="3.5" stroke-linejoin="round"/>
        {!! $eyes(48, 72, 50, 7) !!}{!! $cheeks(38, 82, 62) !!}
        @unless ($shadow)<path d="M53 58 L67 58 L60 67 Z" fill="{{ $beak }}" stroke="{{ $L }}" stroke-width="3" stroke-linejoin="round"/>
        <path d="M50 86 L48 94 M70 86 L72 94" stroke="#FF922B" stroke-width="4" stroke-linecap="round"/>@endunless
@endswitch
</svg>
