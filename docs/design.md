# Find a Sangha design system

Direction "Paper and moss", chosen by Mike 2026-10-26 (Koda's recommendation
over "Plum and stone" and "Saffron and slate"). It grows out of the sand and
moss accents the directory CSS already used, so the site evolves rather than
gets redecorated.

## Tokens (theme.json, fas-child)

Palette:

| Slug | Hex | Role |
| --- | --- | --- |
| paper | #FAF7F0 | Page background |
| paper-deep | #F2ECDB | Hero band, CTA panel, empty states |
| sand | #E7DFC6 | Tradition chips, soft fills |
| line | #DCD2B4 | Borders and hairlines |
| ink | #2E2A23 | Body text, headings |
| ink-soft | #5C5546 | Secondary text, labels |
| moss | #4A6B45 | Primary accent: buttons, focus, verified marker |
| moss-deep | #33482E | Links, hover states, text on moss tint |
| moss-tint | #E3ECDF | Format chips, verified box, focus ring |

Type: Manrope (parent theme, bundled) for UI and body; headings use the
`fas-serif` stack (Literata, Source Serif 4, Georgia) for a bookish,
temple-paper feel. Buttons are moss pills with white text, set globally via
theme.json elements so block buttons and Fluent Forms submit buttons agree.

## Layout rules

**The breathing room rule** (declared by Mike 2026-10-26): no text, form, or
card ever sits flush against the edge of a background it sits on.

- Every background band (hero, CTA panel, any future colored section)
  carries its own inline padding of at least 1.25rem, on the theme spacing
  scale (`--wp--preset--spacing--50`), so the gutter survives at every
  viewport width. A band may not rely on the root page padding: an
  `alignfull` band escapes it.
- Components placed on a band (the search form, cards) keep a further inset
  of at least 1.25rem from the band's content edge.
- Bands keep at least 2.5rem of block (vertical) padding.

Why band-level, not element-level: the hero band shipped with no inline
padding, so the search form inside it ran to the panel's edges even though
the form's own margins were set (WordPress's block layout also forces
`margin-left/right: auto !important` on a constrained group's direct
children, which silently overrides component side margins; inset components
by width or by band padding, not by side margins). The `.fas-hero` band
carries the padding; the form additionally insets itself by width
(`calc(100% - 2.5rem)`). The CTA panel's 1.75rem padding already complies.
The footer has no background fill and relies on root padding.

## The mark

The logo (adopted 2026-10-26, Mike's design, replacing the earlier ensō
sketch) is a hand-drawn moss pebble holding three cream jewels in a
triangle: the Three Jewels, Buddha, Dharma, and Sangha. Two jewels are
solid. **The Sangha jewel (lower left) is an open ring, and that is
intentional, not a rendering error: it is an incomplete refuge, because
the Sangha is yet to be found.** The visitor completes it by finding their
community, which is the site's whole purpose. Do not "fix" the ring into a
solid dot in any cleanup, redraw, or re-export. Source: `docs/fas-logo.svg`
(moss `#4a6b45` on paper-deep `#f2ecdb`, matching the tokens below).

The ensō was retired because it is Zen-specific on a non-sectarian
directory, and at small sizes it read as a loading spinner.

## Components (style.css)

- Header: text wordmark with the Three Jewels mark (see above), plus a
  three-item nav (Browse, Add a sangha, About). Footer part carries the
  verification disclaimer and the full link set.
- Search: the filter form renders as a white panel (line border, soft
  shadow, 16px radius) everywhere it appears; on Home it sits inside the
  paper-deep hero band under the headline.
- Cards: `.fas-results` is a responsive grid; cards are white with a line
  border and lift slightly on hover. Chips: sand for tradition, moss tint
  for format.
- Single listing: details render in a white panel; the verified line gets a
  moss-tint box with a moss left bar; the claim link is an outlined pill CTA.
- Fluent Forms: `.fluentform` overrides match fields, labels, focus rings,
  checkboxes (accent-color), and submit buttons to the tokens.

## Notes

- `[fas_results]` accepts `limit="N"` (plugin 0.4.1) for the Home section;
  pagination is suppressed when a limit is set. Since plugin 0.5.0 it also
  accepts `orderby="confirmed"` (Home "Recently confirmed": sorts by
  last_confirmed_date, newest first), and the filter form carries a
  dedicated Location field (`f_location`: city/address text and
  region/country term names).
- All color pairs used for text were checked for WCAG AA contrast
  (ink on paper ~13:1, ink-soft on paper ~6.9:1, white on moss ~5.9:1,
  moss-deep on moss-tint ~8:1).
- No page builders, no new plugins: theme.json + CSS + template parts only,
  per the settled architecture.
