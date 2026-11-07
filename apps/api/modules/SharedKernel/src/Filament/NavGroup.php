<?php

namespace Modules\SharedKernel\Filament;

use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

/**
 * The back office's menu groups, in their order (docs/architecture/02,
 * "وحدات الأكاديمية"): every screen of the academy panel that shows in the
 * menu names one (getNavigationGroup), except the three at the top (Start
 * here while setup is left, the live board, the calendar). A group whose
 * screens are all closed (an area the academy hid, abilities the viewer
 * lacks) leaves the menu by itself.
 *
 * The items keep their icons (the chrome draws them as chips); Filament
 * takes either the group's icon or its items', so the group's is drawn by
 * shared::chrome.nav-groups beside its label.
 */
enum NavGroup: string
{
    case Lessons = 'lessons';
    case People = 'people';
    case Teaching = 'teaching';
    case Quran = 'quran';
    case Sales = 'sales';
    case Finance = 'finance';
    case Communication = 'communication';
    case Protection = 'protection';
    case Settings = 'settings';

    public function label(): string
    {
        return __('shared::chrome.nav_groups.'.$this->value);
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::Lessons => Heroicon::OutlinedCalendarDays,
            self::People => Heroicon::OutlinedUsers,
            self::Teaching => Heroicon::OutlinedAcademicCap,
            self::Quran => Heroicon::OutlinedBookOpen,
            self::Sales => Heroicon::OutlinedMegaphone,
            self::Finance => Heroicon::OutlinedBanknotes,
            self::Communication => Heroicon::OutlinedChatBubbleLeftRight,
            self::Protection => Heroicon::OutlinedShieldCheck,
            self::Settings => Heroicon::OutlinedCog6Tooth,
        };
    }

    /**
     * Where a screen sits inside its group: its navigationSort is the group's
     * hundred (Lessons 100, People 200, … Settings 900) plus its place (0, 1,
     * 2, …), so the order inside a group reads as the list in
     * docs/architecture/02, "وحدات الأكاديمية".
     */
    public function base(): int
    {
        return ((int) array_search($this, self::cases(), true) + 1) * 100;
    }

    /** @return array<string, NavigationGroup> for Panel::navigationGroups(), keyed by case name as Filament matches an enum group */
    public static function forPanel(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $groups[$case->name] = NavigationGroup::make(fn (): string => $case->label())->collapsible();
        }

        return $groups;
    }
}
