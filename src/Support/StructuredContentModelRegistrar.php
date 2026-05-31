<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support;

use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class StructuredContentModelRegistrar
{
    /** @var list<class-string<Model>> */
    private const array MODELS = [
        StructuredContentItem::class,
    ];

    public static function register(): void
    {
        CapellCore::registerModels(self::MODELS);

        /** @var array<string, class-string<Model>> $morphMap */
        $morphMap = collect(self::MODELS)
            ->mapWithKeys(fn (string $modelClass): array => [Str::snake(class_basename($modelClass)) => $modelClass])
            ->all();

        Relation::morphMap($morphMap);
    }
}
