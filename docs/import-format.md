# FaS CSV import format

One row per sangha. Header row required, lowercase column names as below.
Import: `wp fas import <file.csv> [--dry-run] [--status=draft|publish]`
Default status is draft. A `status` column in the CSV overrides the flag per row.

Columns:
- `title` (required), `description`, `excerpt`, `status`
- Meta: `website_url`, `contact_email`, `meeting_day`, `meeting_time`, `sangha_timezone`,
  `meeting_address`, `city`, `latitude`, `longitude`, `teacher_or_facilitator`,
  `affiliation`, `dana_model`, `source_url`, `last_confirmed_date`, `verified_date`
- Terms (semicolon-separated names, created if missing): `traditions`, `formats`,
  `languages`, `features`, `countries`, `regions`, `lineages`

Duplicate check (spec 4.7): a row is skipped as a duplicate when an existing listing
has the same title AND the same city, or the same website domain. Dry run writes nothing.

Rules:
- `contact_email` is stored but never displayed raw on the front end.
- Home-based sanghas: city-level only. Leave `meeting_address`, `latitude`, and
  `longitude` empty for them.
- `verified_date` means existence and currency were checked by a person. Leave it
  empty until that check has actually happened.
