# Structured Content Library Overview

Structured Content Library is the package-owned source for reusable content concepts that themes currently model themselves.

## Data Model

The initial foundation uses a single `structured_content_items` table. Each record has a `type` enum, publish status, title, slug, optional summary/content, and a typed `payload` JSON object for concept-specific portable metadata.

This keeps the first slice small while still giving themes stable package-owned records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos.

## Content Safety

Database content must stay portable across themes. The create action accepts simple semantic HTML in `content` and rejects designed markup such as classes, styles, IDs, data attributes, and non-semantic wrapper tags.

Admin/editor UI, signed URLs, selectors, package names, and frontend authoring internals must not be stored in these records or emitted by public renderers.

## Next Slices

- Add Filament resources once admin workflow requirements are clear.
- Add theme/content-section adapters that query this package instead of demo-shaped data.
- Add migration/import actions for existing demo and theme content.
