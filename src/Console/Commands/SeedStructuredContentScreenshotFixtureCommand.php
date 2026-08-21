<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Console\Commands;

use Capell\StructuredContentLibrary\Actions\SeedStructuredContentScreenshotFixtureAction;
use Illuminate\Console\Command;
use Throwable;

final class SeedStructuredContentScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:structured-content-library-screenshot-fixture {--force : Confirm an intentional disposable screenshot seed}';

    protected $description = 'Seed Structured Content Library record state for an explicit disposable screenshot run';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to seed screenshot fixtures without --force.');

            return self::FAILURE;
        }

        try {
            SeedStructuredContentScreenshotFixtureAction::run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Structured Content Library screenshot fixture initialized.');

        return self::SUCCESS;
    }
}
