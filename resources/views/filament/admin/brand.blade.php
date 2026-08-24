{{--
    The panel's brand mark: a fuel gauge and a wordmark.

    Drawn inline rather than loaded as an image so it inherits the current text
    colour and therefore reads correctly in both themes without a second,
    dark-mode asset. The needle sits just past the halfway mark, which is where
    a gauge is most obviously a gauge — a full or empty one reads as a plain arc.
--}}
<span
    style="display: inline-flex; align-items: center; gap: 0.5rem; line-height: 1;"
>
    <svg
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
        style="flex-shrink: 0"
    >
        {{-- Dial --}}
        <path
            d="M4 17a8 8 0 0 1 16 0"
            stroke="currentColor"
            stroke-opacity="0.35"
            stroke-width="1.75"
            stroke-linecap="round"
        />
        {{-- Empty and full ticks --}}
        <path
            d="M4 17h1.6M18.4 17H20"
            stroke="currentColor"
            stroke-opacity="0.35"
            stroke-width="1.75"
            stroke-linecap="round"
        />
        {{-- Needle --}}
        <path
            d="M12 17l3.6-4.6"
            stroke="var(--primary-500, currentColor)"
            stroke-width="2"
            stroke-linecap="round"
        />
        <circle cx="12" cy="17" r="1.6" fill="currentColor" />
    </svg>

    <span style="font-weight: 600; letter-spacing: -0.02em;">
        Car&nbsp;Tracker
        <span style="color: var(--primary-600, currentColor);">Ops</span>
    </span>
</span>
