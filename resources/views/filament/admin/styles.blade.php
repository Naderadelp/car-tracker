{{--
    Panel refinements for Car Tracker Ops.

    Injected at PanelsRenderHook::STYLES_AFTER — immediately after Filament
    writes its own `:root` block — rather than shipped as a compiled theme, so
    the panel keeps working from a plain `composer install` with no npm build
    in the deploy path. It is deliberately short: every rule here either makes
    a number easier to read or gives a label the character of an instrument
    panel. Only class names Filament actually renders are targeted.
--}}
<style>
    /*
     * Figures on this panel are readings — kilometres, litres, Egyptian
     * pounds. Setting them in the mono face with tabular figures stops a
     * value from jittering sideways as it ticks over, and lines stacked
     * numbers up into a column you can scan.
     */
    .fi-wi-stats-overview-stat-value {
        font-family: var(--mono-font-family), ui-monospace, "SFMono-Regular", monospace;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.035em;
        /*
         * 700, not the 600 the rest of the panel uses: Space Mono only draws
         * regular and bold, and asking for a weight it does not have gets a
         * synthesised one, which at this size looks smeared rather than bold.
         */
        font-weight: 700;
    }

    .fi-ta-table {
        font-variant-numeric: tabular-nums;
    }

    /*
     * Labels are the engraving around the dial, not the reading: small, wide,
     * and quiet enough that the value keeps the eye.
     */
    .fi-wi-stats-overview-stat-label,
    .fi-sidebar-group-label {
        text-transform: uppercase;
        letter-spacing: 0.07em;
        font-size: 0.6875rem;
        font-weight: 600;
    }

    /*
     * Space Grotesk sets a little loose at heading sizes; closing it up is
     * what stops the display face reading as a default.
     */
    .fi-section-header-heading {
        letter-spacing: -0.015em;
    }
</style>
