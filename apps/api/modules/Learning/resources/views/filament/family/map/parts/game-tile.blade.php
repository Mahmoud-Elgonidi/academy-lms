@php
    /* A game's picture on its card (the islands design): by the game, else by what it plays. */
    $art = ['bird-gates' => 'gates', 'bird-balloons' => 'balloons', 'bird-twins' => 'cards', 'turtle-bridge' => 'bridge'][$g['key']]
        ?? (in_array('pair', $g['kinds'], true) ? 'cards' : (in_array('choice', $g['kinds'], true) ? 'balloons' : 'gates'));
    $paint = ['gates' => ['#D0EBFF', '#74C0FC'], 'balloons' => ['#FFE8CC', '#FFA94D'], 'cards' => ['#C3FAE8', '#38D9A9'], 'bridge' => ['#C3FAE8', '#63E6BE']][$art];
@endphp
<span class="gw-tile" style="background: {{ $paint[1] }}">
    @switch($art)
        @case('gates')<svg width="50" height="44" viewBox="0 0 50 44" aria-hidden="true"><ellipse cx="34" cy="22" rx="10" ry="16" fill="none" stroke="#FFD43B" stroke-width="5"/><g transform="translate(2 12) scale(0.3)"><ellipse cx="50" cy="44" rx="30" ry="25" fill="#FFFFFF" stroke="#13294B" stroke-width="7"/><path d="M22 40 L6 46 L22 52 Z" fill="#FF922B"/><path d="M48 40 C50 14 80 8 84 24 C78 34 66 42 56 46 Z" fill="#4DABF7" stroke="#13294B" stroke-width="7"/></g></svg>@break
        @case('balloons')<svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true"><ellipse cx="16" cy="18" rx="11" ry="13" fill="#FF6B6B" stroke="#13294B" stroke-width="2.5"/><ellipse cx="32" cy="16" rx="10" ry="12" fill="#38D9A9" stroke="#13294B" stroke-width="2.5"/><path d="M16 31 Q18 38 15 44 M32 28 Q34 36 31 44" stroke="#13294B" stroke-width="1.6" fill="none"/></svg>@break
        @case('cards')<svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true"><g transform="rotate(-12 18 24)"><rect x="6" y="8" width="22" height="30" rx="4" fill="#6236E0" stroke="#13294B" stroke-width="2.5"/></g><g transform="rotate(10 30 24)"><rect x="20" y="6" width="22" height="30" rx="4" fill="#FFFFFF" stroke="#13294B" stroke-width="2.5"/><path d="M24 16 h14 M24 22 h10" stroke="#13294B" stroke-width="2.5" stroke-linecap="round"/></g></svg>@break
        @default<svg width="50" height="40" viewBox="0 0 90 60" aria-hidden="true"><ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" stroke-width="3"/><ellipse cx="48" cy="34" rx="28" ry="20" fill="#2F9E44" stroke="#13294B" stroke-width="3"/><circle cx="48" cy="34" r="6" fill="#FFFFFF"/></svg>
    @endswitch
</span>
