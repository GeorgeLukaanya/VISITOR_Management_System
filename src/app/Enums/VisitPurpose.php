<?php

namespace App\Enums;

/**
 * The fixed purpose menu shown on USSD screen 2 (see CLAUDE.md two-screen flow).
 * The integer is what the visitor types; the value is what we store/display.
 */
enum VisitPurpose: int
{
    case Meeting = 1;
    case Delivery = 2;
    case Interview = 3;
    case Other = 4;

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Meeting',
            self::Delivery => 'Delivery',
            self::Interview => 'Interview',
            self::Other => 'Other',
        };
    }

    /** Resolve a visitor's raw menu input ("1".."4") to a purpose, or null if invalid. */
    public static function fromInput(string $input): ?self
    {
        $input = trim($input);

        if ($input === '' || ! ctype_digit($input)) {
            return null;
        }

        return self::tryFrom((int) $input);
    }

    /** The menu line rendered on screen 2: "1. Meeting 2. Delivery ...". */
    public static function menu(): string
    {
        return collect(self::cases())
            ->map(fn (self $p) => $p->value.'. '.$p->label())
            ->implode(' ');
    }
}
