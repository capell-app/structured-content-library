<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static void run(StructuredContentType $type, ?StructuredContentPayloadData $payload) */
final class ValidateStructuredContentPayloadAction
{
    use AsObject;

    public function handle(StructuredContentType $type, ?StructuredContentPayloadData $payload): void
    {
        $definition = StructuredContentDefinitionData::forType($type);
        $values = $payload?->toArray() ?? [];
        $rules = [];
        $attributes = [];

        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '' && ! in_array($key, $definition->fieldNames(), true)) {
                throw ValidationException::withMessages([
                    'payload.' . $key => __('capell-structured-content-library::validation.incompatible_payload'),
                ]);
            }
        }

        foreach ($definition->fields as $field) {
            $rules['payload.' . $field->value] = $field->rules();
            $attributes['payload.' . $field->value] = $field->label();
        }

        Validator::make(['payload' => $values], $rules, attributes: $attributes)->validate();
    }
}
