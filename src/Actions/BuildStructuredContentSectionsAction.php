<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentSectionData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildStructuredContentSectionsAction
{
    use AsObject;

    /**
     * @param  array<string, StructuredContentType|array{type: StructuredContentType|string, label?: string, limit?: int|null}>  $sections
     * @return list<StructuredContentSectionData>
     */
    public function handle(array $sections, ?int $siteId = null): array
    {
        $resolvedSections = [];

        foreach ($sections as $key => $definition) {
            $type = $this->type($definition);
            $items = BuildPublicStructuredContentItemsAction::run($type, $siteId, $this->limit($definition));

            if ($items === []) {
                continue;
            }

            $resolvedSections[] = new StructuredContentSectionData(
                key: (string) $key,
                type: $type,
                label: $this->label($definition, $type),
                items: $items,
            );
        }

        return $resolvedSections;
    }

    /**
     * @param  StructuredContentType|array{type: StructuredContentType|string, label?: string, limit?: int|null}  $definition
     */
    private function type(StructuredContentType|array $definition): StructuredContentType
    {
        if ($definition instanceof StructuredContentType) {
            return $definition;
        }

        $type = $definition['type'];

        return $type instanceof StructuredContentType ? $type : StructuredContentType::from($type);
    }

    /**
     * @param  StructuredContentType|array{type: StructuredContentType|string, label?: string, limit?: int|null}  $definition
     */
    private function label(StructuredContentType|array $definition, StructuredContentType $type): string
    {
        if (is_array($definition) && isset($definition['label']) && trim($definition['label']) !== '') {
            return $definition['label'];
        }

        return $type->getLabel();
    }

    /**
     * @param  StructuredContentType|array{type: StructuredContentType|string, label?: string, limit?: int|null}  $definition
     */
    private function limit(StructuredContentType|array $definition): ?int
    {
        if (! is_array($definition)) {
            return null;
        }

        return isset($definition['limit']) && is_int($definition['limit']) ? $definition['limit'] : null;
    }
}
