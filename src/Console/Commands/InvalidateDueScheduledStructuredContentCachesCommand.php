<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Console\Commands;

use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Actions\InvalidateDueScheduledStructuredContentCachesAction;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Illuminate\Console\Command;

final class InvalidateDueScheduledStructuredContentCachesCommand extends Command
{
    protected $signature = 'structured-content-library:invalidate-due-caches';

    protected $description = 'Refresh cached pages for structured content records whose publication time has just passed.';

    public function handle(): int
    {
        if (! CapellCore::isPackageEnabled(StructuredContentLibraryServiceProvider::$packageName)) {
            return self::SUCCESS;
        }

        $count = InvalidateDueScheduledStructuredContentCachesAction::run();
        $this->components->info(trans_choice('capell-structured-content-library::admin.invalidated_due_caches', $count, ['count' => $count]));

        return self::SUCCESS;
    }
}
