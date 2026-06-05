# Structured Content Library

Structured Content Library owns reusable business-content records for Capell themes and packages.

The package stores portable content for:

- case studies
- testimonials
- team members
- services
- FAQs
- resources
- partners
- locations
- logos

It intentionally does not store designed layout markup, Tailwind classes, frontend authoring markers, or theme-specific structures. Themes should render the model data through their own Blade/components.

## Foundation

The first slice provides:

- `StructuredContentItem`, a package-owned Eloquent model backed by `structured_content_items`
- `StructuredContentType`, a backed enum for the supported content concepts
- `StructuredContentStatus`, a backed enum for draft/published/archive state
- `StructuredContentItemData` and `StructuredContentPayloadData` DTOs for structured writes and JSON payloads
- Actions for creating, updating, importing, listing, and building public-safe section payloads by type/site

Use `ListStructuredContentItemsAction::run($type, $siteId)` when a theme or package needs published reusable records.
Use `BuildStructuredContentSectionsAction::run($sections, $siteId)` when a theme or content-section package needs grouped section-ready payloads.

Public adapter payloads are filtered before they leave the package boundary: scalar payload fields are emitted as plain text, `url` must be HTTP(S) or a root-relative URL, and `email` must validate as an email address. Themes should still render values with normal Blade escaping unless they are intentionally rendering the already-validated `content` or `summary` portable HTML fields.

When a record is saved as published without an explicit `published_at`, the package stores the current timestamp so ordering and audit trails can distinguish newly published records from drafts.

Slugs are normalized and kept unique within each content type and site scope. If a generated or supplied slug is already used by another active or soft-deleted record, the write actions append a numeric suffix such as `-2` before saving.

Marketplace screenshot coverage is declared in `docs/screenshots.json`; the
three required captures are committed under `docs/screenshots/` and listed in
`capell.json` with the extension card.
