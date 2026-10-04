# Extension and action examples

<!-- Maintained by scripts/generate-package-readmes.php -->

Use the action functions with records and Data objects supplied by your application.
They pass each argument to the package operation and return its result.

These adapters show container registration. Use the owning package registry when
a contract requires contributor discovery.

Contract adapters wrap an existing implementation. Call their registration function
from your service provider with that implementation; tagged contracts keep their declared tag.
Resolve the backend by its concrete class before registration so the replacement contract
does not resolve itself. Static contract metadata uses one backend class per adapter.

## Action `buildPublicStructuredContentItems`

<!-- example: action buildPublicStructuredContentItems -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\StructuredContentLibrary;

function runBuildPublicStructuredContentItems(\Capell\StructuredContentLibrary\Enums\StructuredContentType $type, ?int $siteId = null, ?int $limit = null): array
{
    return \Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction::run($type, $siteId, $limit);
}
```

## Action `buildStructuredContentSections`

<!-- example: action buildStructuredContentSections -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\StructuredContentLibrary;

function runBuildStructuredContentSections(array $sections, ?int $siteId = null): array
{
    return \Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction::run($sections, $siteId);
}
```

## Action `importStructuredContentItems`

<!-- example: action importStructuredContentItems -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\StructuredContentLibrary;

function runImportStructuredContentItems(iterable $items, bool $updateExisting = true): \Capell\StructuredContentLibrary\Data\StructuredContentImportResultData
{
    return \Capell\StructuredContentLibrary\Actions\ImportStructuredContentItemsAction::run($items, $updateExisting);
}
```

## Action `listStructuredContentItems`

<!-- example: action listStructuredContentItems -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\StructuredContentLibrary;

function runListStructuredContentItems(\Capell\StructuredContentLibrary\Enums\StructuredContentType $type, ?int $siteId = null, bool $publishedOnly = true): \Illuminate\Support\Collection
{
    return \Capell\StructuredContentLibrary\Actions\ListStructuredContentItemsAction::run($type, $siteId, $publishedOnly);
}
```
