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
- Actions for creating records and listing visible records by type/site

Use `ListStructuredContentItemsAction::run($type, $siteId)` when a theme or package needs published reusable records.
