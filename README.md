# Structured Content Library

Structured Content Library owns reusable, portable business-content records for Capell themes, Content Sections, and package adapters.

## At A Glance

| Field            | Value                                                                               |
| ---------------- | ----------------------------------------------------------------------------------- |
| Composer package | `capell-app/structured-content-library`                                             |
| Namespace        | `Capell\StructuredContentLibrary`                                                   |
| Product group    | Capell Content                                                                      |
| Surfaces         | Admin, shared runtime Actions                                                       |
| Provider         | `Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider` |
| Admin resource   | `StructuredContentItemResource`                                                     |
| Table            | `structured_content_items`                                                          |
| Supports         | Content Sections and Foundation Theme through public-safe section payloads          |

## Why It Helps Your Capell Workflow

Owners get reusable content records for services, testimonials, FAQs, partners, locations, logos, team members, case studies, and resources without locking that content to one page layout.

Editors manage structured records once and let themes/packages render them wherever needed. Developers get typed Actions and DTOs for public-safe item lists and grouped section payloads instead of storing designed markup in database content fields.

## What It Adds

- `StructuredContentItem` model with draft, published, and archived status.
- `StructuredContentType` enum for the supported reusable content concepts.
- `StructuredContentItemResource` Filament admin resource.
- Actions for create, update, import, list, slug resolution, public item output, and grouped section output.
- DTOs for write boundaries, public payloads, import results, and section data.
- Cache invalidation hooks for structured-content frontend dependencies where the frontend cache registry is available.

## Boundaries

The package stores portable content only. It must not store designed layout wrappers, Tailwind classes, frontend authoring markers, theme-specific HTML structures, or package internals in content fields.

Themes and Content Sections should consume `PublicStructuredContentItemData` or `StructuredContentSectionData` and render their own presentation. Public adapter payloads filter scalar fields, validate URLs/emails, and keep package metadata out of frontend output.

## Runtime Surface

- Provider: `src/Providers/StructuredContentLibraryServiceProvider.php`
- Admin resource: `src/Filament/Resources/StructuredContentItems/`
- Actions: `src/Actions/`
- Data objects: `src/Data/`
- Enums: `src/Enums/`
- Model: `src/Models/StructuredContentItem.php`
- Cache support: `src/Support/StructuredContentCache.php`
- Tests: `packages/structured-content-library/tests`

## Docs

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Improvement plan](docs/improvement-plan.md)
- [Screenshots contract](docs/screenshots.json)

## Testing

```bash
vendor/bin/pest packages/structured-content-library/tests --configuration=phpunit.xml
```

## Troubleshooting

| Symptom                           | Likely cause                                                                            | Check                                                                        | Fix                                                             |
| --------------------------------- | --------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- | --------------------------------------------------------------- |
| Public adapter returns no records | Records are drafts, unpublished, outside site scope, or filtered by type                | Check `structured_content_items.status`, `published_at`, `site_id`, and type | Publish the record or adjust the Action call scope              |
| Save rejects content              | `EnsurePortableContentHtmlAction` found presentation markup or unsafe HTML              | Check the validation message and content field                               | Store semantic HTML only and move layout/classes into the theme |
| Slug gets a suffix                | Another active or soft-deleted record already owns the slug in the same type/site scope | Query `structured_content_items` by type, site, and slug                     | Choose a unique slug or restore/update the existing record      |
