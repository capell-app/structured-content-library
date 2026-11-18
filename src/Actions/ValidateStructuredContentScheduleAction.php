<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A record must stop showing after it starts: an expiry at or before the publication time would
 * hide it for good the moment it is saved.
 *
 * @method static void run(?CarbonInterface $publishedAt, ?CarbonInterface $expiresAt)
 */
final class ValidateStructuredContentScheduleAction
{
    use AsFake;
    use AsObject;

    public function handle(?CarbonInterface $publishedAt, ?CarbonInterface $expiresAt): void
    {
        if ($publishedAt instanceof CarbonInterface && $expiresAt instanceof CarbonInterface && $expiresAt->lessThanOrEqualTo($publishedAt)) {
            throw ValidationException::withMessages([
                'expires_at' => __('capell-structured-content-library::validation.expires_after_publication'),
            ]);
        }
    }
}
