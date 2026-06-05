# Structured Content Library Overview

Structured Content Library is the package-owned source for reusable content concepts that themes currently model themselves.

## Data Model

The initial foundation uses a single `structured_content_items` table. Each record has a `type` enum, publish status, title, slug, optional summary/content, and a typed `payload` JSON object for concept-specific portable metadata.

This keeps the first slice small while still giving themes stable package-owned records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos.

`BuildPublicStructuredContentItemsAction` exposes published, site-aware, type-filtered DTOs for themes and content-section adapters. The DTO output omits model IDs, site IDs, admin fields, package names, and editor metadata.

Payload values in public DTOs are filtered at the package boundary. Text-like payload fields are reduced to plain text, unsafe URL schemes are removed, and emails must validate before they are emitted. This keeps the adapter safe even when downstream theme code consumes reusable fields in different section templates.

`BuildStructuredContentSectionsAction` groups those public DTOs into section-ready payloads for themes and content-section packages. Consumers pass section keys with content types, labels, and limits; the action returns only sections that have public items.

`ImportStructuredContentItemsAction` gives demo kits, themes, and migration tools a package-owned importer for moving existing reusable content into this table. Imports reuse the create/update action boundaries, so portable HTML validation and slug normalization stay consistent.

Create and update writes also resolve slug collisions before persistence. Slug uniqueness is scoped to the content type and site, includes soft-deleted records so restores remain predictable, and appends numeric suffixes when a title or supplied slug is already taken.

## Content Safety

Database content must stay portable across themes. The create and update actions accept simple semantic HTML in `content` and `summary`, and reject designed markup such as classes, styles, IDs, data attributes, inline handlers, and non-semantic wrapper tags.

Admin/editor UI, signed URLs, selectors, package names, and frontend authoring internals must not be stored in these records or emitted by public renderers.

## Integration

Owning theme and content-section packages should consume `BuildStructuredContentSectionsAction` instead of storing demo-shaped business content. This package owns the reusable records, public-safe DTOs, and section adapter payload shape; themes remain responsible for their rendered presentation.

Package-specific import commands can wrap `ImportStructuredContentItemsAction` when a theme or demo package has enough known content to migrate automatically.

## Screenshot Coverage

`docs/screenshots.json` now declares the three required marketplace captures:
the admin item list, the create form with typed payload fields, and a
theme-rendered section consuming published structured content. All three
1440x900 PNGs are committed under `docs/screenshots/` and listed in
`capell.json` marketplace media with the extension card.
