<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentSectionData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<StructuredContentSectionData> run(array<string, StructuredContentType|array{type: StructuredContentType|string, label?: string, limit?: int|null}> $sections, ?int $siteId = null)
 */
final class BuildStructuredContentSectionsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, StructuredContentType|array{type: StructuredContentType|string, label?: string, limit?: int|null}>  $sections
     * @return list<StructuredContentSectionData>
     */
    public function handle(array $sections, ?int $siteId = null): array
    {
        $definitions = [];

        foreach ($sections as $key => $definition) {
            $type = $this->type($definition);
            $definitions[] = [
                'key' => (string) $key,
                'type' => $type,
                'label' => $this->label($definition, $type),
                'limit' => $this->limit($definition),
            ];
        }

        $itemsByType = $this->itemsByType($definitions, $siteId);
        $resolvedSections = [];

        foreach ($definitions as $definition) {
            /** @var StructuredContentType $type */
            $type = $definition['type'];
            $items = $itemsByType[$type->value] ?? [];
            $limit = $definition['limit'];

            if (is_int($limit)) {
                $items = array_slice($items, 0, max(0, $limit));
            }

            if ($items === []) {
                continue;
            }

            $resolvedSections[] = new StructuredContentSectionData(
                key: $definition['key'],
                type: $type,
                label: $definition['label'],
                items: $items,
            );
        }

        return $resolvedSections;
    }

    /**
     * Reads each type once. A type whose every section is limited reads only as many records as
     * its largest section shows; the others are read together in full.
     *
     * @param  list<array{key: string, type: StructuredContentType, label: string, limit: int|null}>  $definitions
     * @return array<string, list<PublicStructuredContentItemData>>
     */
    private function itemsByType(array $definitions, ?int $siteId): array
    {
        /** @var array<string, array{type: StructuredContentType, limit: int|null}> $reads */
        $reads = [];

        foreach ($definitions as $definition) {
            $type = $definition['type'];
            $limit = $definition['limit'] === null ? null : max(0, $definition['limit']);
            $current = $reads[$type->value] ?? null;

            $reads[$type->value] = [
                'type' => $type,
                'limit' => $current === null
                    ? $limit
                    : ($current['limit'] === null || $limit === null ? null : max($current['limit'], $limit)),
            ];
        }

        $unlimited = [];
        $itemsByType = [];
        $builder = BuildPublicStructuredContentItemsForTypesAction::make();

        foreach ($reads as $read) {
            if ($read['limit'] === null) {
                $unlimited[] = $read['type'];

                continue;
            }

            if ($read['limit'] > 0) {
                $itemsByType = [...$itemsByType, ...$builder->limited($read['type'], $siteId, $read['limit'])];
            }
        }

        if ($unlimited !== []) {
            $itemsByType = [...$itemsByType, ...BuildPublicStructuredContentItemsForTypesAction::run($unlimited, $siteId)];
        }

        return $itemsByType;
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
