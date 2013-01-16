<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentPayloadField;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, string> run(?StructuredContentPayloadData $payload, ?StructuredContentType $type = null)
 */
final class BuildPublicStructuredContentPayloadAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<string, string>
     */
    public function handle(?StructuredContentPayloadData $payload, ?StructuredContentType $type = null): array
    {
        if (! $payload instanceof StructuredContentPayloadData) {
            return [];
        }

        $publicPayload = [];

        $fields = $type === null
            ? StructuredContentPayloadField::cases()
            : StructuredContentDefinitionData::forType($type)->fields;
        $values = $payload->toArray();

        foreach ($fields as $field) {
            $value = $values[$field->value] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $publicValue = $field->publicValue($value);

            if ($publicValue !== null) {
                $publicPayload[$field->value] = $publicValue;
            }
        }

        return $publicPayload;
    }
}
