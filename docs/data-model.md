# FaS data model (summary)

Full spec: `../../SCAFFOLD_AND_PLUGIN_SPEC.md` section 4.

- Post type `sangha_listing` (public, slug `sanghas`)
- Post type `fas_review` (private UI, structured visitor reviews, no aggregate star score in MVP)
- Post type `fas_claim` (private, listing claim queue)
- Taxonomies: `tradition`, `lineage_or_school`, `format`, `language`, `country`, `region`, `features`
- Listing status flow: draft → pending verification → published → needs reconfirmation → archived
- Verification = existence and currency checked. Never endorsement of a teacher.
- Home-based sanghas: city-level listing only on request, no exact address, no home map pin.
- Featured: always labeled, expiry date required, region/format placement only.
