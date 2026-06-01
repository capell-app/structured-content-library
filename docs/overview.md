# Structured Content Library Overview

Structured Content Library is the package-owned source for reusable content concepts that themes currently model themselves.

## Data Model

The initial foundation uses a single `structured_content_items` table. Each record has a `type` enum, publish status, title, slug, optional summary/content, and a typed `payload` JSON object for concept-specific portable metadata.

This keeps the first slice small while still giving themes stable package-owned records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos.

`BuildPublicStructuredContentItemsAction` exposes published, site-aware, type-filtered DTOs for themes and content-section adapters. The DTO output omits model IDs, site IDs, admin fields, package names, and editor metadata.

`BuildStructuredContentSectionsAction` groups those public DTOs into section-ready payloads for themes and content-section packages. Consumers pass section keys with content types, labels, and limits; the action returns only sections that have public items.

`ImportStructuredContentItemsAction` gives demo kits, themes, and migration tools a package-owned importer for moving existing reusable content into this table. Imports reuse the create/update action boundaries, so portable HTML validation and slug normalization stay consistent.

## Content Safety

Database content must stay portable across themes. The create action accepts simple semantic HTML in `content` and rejects designed markup such as classes, styles, IDs, data attributes, and non-semantic wrapper tags.

Admin/editor UI, signed URLs, selectors, package names, and frontend authoring internals must not be stored in these records or emitted by public renderers.

## Next Slices

- Wire owning theme/content-section packages to `BuildStructuredContentSectionsAction` instead of demo-shaped data.
- Add package-specific import commands where a theme or demo package has enough known content to migrate automatically.
