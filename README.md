# Find a Sangha (FaS) — findasangha.com

Global, searchable directory of Buddhist sanghas: online, in person, and hybrid.
Free basic listings forever. Distinct from Virtual Sangha (which is a founding listing).

## Layout
- `wp-content/plugins/fas-directory/` — custom directory plugin, owns the data model in code
- `wp-content/themes/fas-child/` — child of Twenty Twenty-Five, presentation only
- `docs/` — data model, import format, moderation policy

## Architecture (settled 2026-10-25)
- Block theme (Twenty Twenty-Five) + child theme. No page builders.
- Git is the source of truth. Deploy = pull/rsync over SSH, never in-place server edits.
- WP-CLI first for installs, options, and seeding.
- Maps: Leaflet + OpenStreetMap. No Google Maps key.
- Online sangha times: sangha timezone stored and displayed plainly in MVP; visitor conversion is Phase 2.
- Seed data goes through the CSV importer, even for the first batch (importer is Phase 1; the first 3 listings are added by hand to prove the model).

Spec: `../SCAFFOLD_AND_PLUGIN_SPEC.md`
