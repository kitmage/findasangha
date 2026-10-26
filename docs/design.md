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

## Components (style.css)

- Header: text wordmark with an open-circle (enso-like) SVG mark in moss,
  plus a three-item nav (Browse, Add a sangha, About). Footer part carries
  the verification disclaimer and the full link set.
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

- `[fas_results]` accepts `limit="N"` (plugin 0.4.1) for the Home "Newest"
  section; pagination is suppressed when a limit is set.
- All color pairs used for text were checked for WCAG AA contrast
  (ink on paper ~13:1, ink-soft on paper ~6.9:1, white on moss ~5.9:1,
  moss-deep on moss-tint ~8:1).
- No page builders, no new plugins: theme.json + CSS + template parts only,
  per the settled architecture.
