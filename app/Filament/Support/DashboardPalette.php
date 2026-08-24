<?php

namespace App\Filament\Support;

/**
 * The dashboard's chart colours, in one place so every widget reads as one system.
 *
 * Chart.js is handed literal colours — it cannot resolve the `--primary-600`
 * custom property Filament writes onto `:root` — so these constants have to be
 * kept in step with the panel's own ramp by hand. They are the hex forms of
 * the shades AdminPanelProvider generates: change the panel's primary and
 * these have to move with it.
 *
 * Two rules hold the choices together.
 *
 * First, amber is never decoration. The panel used to run Amber as its primary
 * colour, which meant a link, a submit button and a warning all looked the
 * same; the chrome is petrol now, and amber appears only where something needs
 * attention or on the secondary axis it shares with the warning lane.
 *
 * Second, fuel grades keep the colours of the pumps they come from — 92 green,
 * 95 red, electric blue — so the grade split can be read before anyone
 * consults the legend. Series that are not categories (a brand ranking, a
 * spend line) get one colour, because colouring a ranking implies a
 * distinction that is not in the data.
 *
 * Shade choice follows the mark. Thin marks — lines, dashes — take the 600
 * shades, which clear a 3:1 contrast ratio against both the light and the dark
 * section background. Large fills — bars, doughnut slices — take the more
 * saturated 500s, where area carries the colour and contrast is not the
 * binding constraint.
 */
final class DashboardPalette
{
    /** Panel chrome, and the primary measure on any chart. `--primary-600`. */
    public const PETROL = '#0097b8';

    public const PETROL_FILL = 'rgba(0, 151, 184, 0.14)';

    /** Attention only: the volume axis, and anything the warning lane owns. Amber 600. */
    public const SIGNAL = '#e17100';

    public const SIGNAL_FILL = 'rgba(225, 113, 0, 0.10)';

    /** A quiet companion series — present, but not competing for the eye. Slate 500. */
    public const SLATE = '#62748e';

    /**
     * Fill-up fuel grades, keyed by the `fuel_type` enum stored on `fill_ups`.
     *
     * Emerald, Red and Sky at 500 — the panel's own hues, standing in for the
     * green, red and blue the pumps themselves are labelled with.
     *
     * @var array<string, string>
     */
    public const FUEL_GRADES = [
        '92' => '#00bc7d',
        '95' => '#fb2c36',
        'electric' => '#00a6f4',
    ];

    /**
     * Display names for the same enum.
     *
     * @var array<string, string>
     */
    public const FUEL_GRADE_LABELS = [
        '92' => '92 octane',
        '95' => '95 octane',
        'electric' => 'Electric',
    ];

    public static function fuelGradeColor(?string $type): string
    {
        return self::FUEL_GRADES[$type] ?? self::SLATE;
    }

    public static function fuelGradeLabel(?string $type): string
    {
        return self::FUEL_GRADE_LABELS[$type] ?? 'Unrecorded';
    }
}
