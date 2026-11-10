# Structured Content Library reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/structured-content-library`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Structured Content Library is an **Available**, **Schema-owning** Capell package in the **Capell Essentials** product group. It ships as `capell-app/structured-content-library` and extends these surfaces: admin, shared.

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/structured-content-library`
- Namespace: `Capell\StructuredContentLibrary`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\StructuredContentLibrary\Providers\MigrationMetadataServiceProvider`
- `Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider`

### Migrations

- `packages/structured-content-library/database/migrations/2026_05_31_000001_create_structured_content_items_table.php`
- `packages/structured-content-library/database/migrations/2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table.php`
- `packages/structured-content-library/database/migrations/2026_07_10_000001_add_normalized_scope_key_to_structured_content_items.php`

### Models

- `StructuredContentItem`

### Filament classes

- `CreateStructuredContentItem`
- `EditStructuredContentItem`
- `ListStructuredContentItems`
- `StructuredContentItemResource`

### Policies

- `StructuredContentItemPolicy`

### Actions

- `AuthorizeStructuredContentMutationAction`
- `BuildPublicStructuredContentItemDataAction`
- `BuildPublicStructuredContentItemsAction`
- `BuildPublicStructuredContentItemsForTypesAction`
- `BuildPublicStructuredContentPayloadAction`
- `BuildStructuredContentSectionsAction`
- `CreateStructuredContentItemAction`
- `DescribeStructuredContentAvailabilityAction`
- `EnsurePortableContentHtmlAction`
- `EnsurePortableEditorDocumentAction`
- `ImportStructuredContentItemsAction`
- `InvalidateStructuredContentOrderAction`
- `ListStructuredContentItemsAction`
- `MergeStructuredContentReorderAction`
- `ResolveStructuredContentCacheExpiryAction`
- `ResolveUniqueStructuredContentSlugAction`
- `SeedStructuredContentScreenshotFixtureAction`
- `UpdateStructuredContentItemAction`
- `ValidateStructuredContentPayloadAction`

### Data objects

- `PublicStructuredContentItemData`
- `StructuredContentDefinitionData`
- `StructuredContentImportResultData`
- `StructuredContentItemData`
- `StructuredContentPayloadData`
- `StructuredContentSectionData`

### Manifest action API

- `buildPublicStructuredContentItems: Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction`
- `buildStructuredContentSections: Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction`
- `importStructuredContentItems: Capell\StructuredContentLibrary\Actions\ImportStructuredContentItemsAction`
- `listStructuredContentItems: Capell\StructuredContentLibrary\Actions\ListStructuredContentItemsAction`

### Console command classes

- `SeedStructuredContentScreenshotFixtureCommand`

### Manifest contributions

- `admin-resource: Capell\StructuredContentLibrary\Manifest\StructuredContentItemResourceContribution`
- `agent-capability: Capell\StructuredContentLibrary\Manifest\StructuredContentSectionAdapterContribution`
- `agent-capability: Capell\StructuredContentLibrary\Manifest\StructuredContentThemeAdapterContribution`
- `asset: Capell\StructuredContentLibrary\Manifest\StructuredContentAssetsContribution`
- `model: Capell\StructuredContentLibrary\Manifest\StructuredContentModelsContribution`

### Health checks

- `Capell\StructuredContentLibrary\Health\StructuredContentLibraryHealthCheck`

### Blade views

- `packages/structured-content-library/resources/views/admin/preview.blade.php`
- `packages/structured-content-library/resources/views/forms/type-cards.blade.php`

### Cache tags

- `structured-content-library`


## Data Model

- Required tables: `structured_content_items`.
- Models: `StructuredContentItem`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_05_31_000001_create_structured_content_items_table.php`, `2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table.php`, `2026_07_10_000001_add_normalized_scope_key_to_structured_content_items.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare null-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

<!-- prettier-ignore-end -->
