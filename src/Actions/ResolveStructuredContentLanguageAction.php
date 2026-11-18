<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The language a public render is in. The frontend applies a site language's locale, or its code
 * when it has none, as the application locale; this finds that language again.
 *
 * @method static Language|null run(string $locale)
 */
final class ResolveStructuredContentLanguageAction
{
    use AsFake;
    use AsObject;

    public function handle(string $locale): ?Language
    {
        $locale = trim($locale);

        if ($locale === '') {
            return null;
        }

        $byLocale = Language::query()->where('locale', $locale)->orderBy('id')->first();

        if ($byLocale instanceof Language) {
            return $byLocale;
        }

        $byCode = Language::query()
            ->where('code', $locale)
            ->where(static function (Builder $query): void {
                $query->whereNull('locale')->orWhere('locale', '');
            })
            ->orderBy('id')
            ->first();

        return $byCode instanceof Language ? $byCode : null;
    }
}
