<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('builds item data with snake case payload fields', function (): void {
    $data = StructuredContentItemData::from([
        'type' => StructuredContentType::Faq->value,
        'title' => 'Common question',
        'status' => StructuredContentStatus::Published->value,
        'payload' => [
            'question' => 'How does this render?',
            'answer' => 'Through theme-owned views.',
            'resource_kind' => 'guide',
        ],
    ]);

    expect($data->type)->toBe(StructuredContentType::Faq)
        ->and($data->status)->toBe(StructuredContentStatus::Published)
        ->and($data->payload)->toBeInstanceOf(StructuredContentPayloadData::class)
        ->and($data->payload?->resourceKind)->toBe('guide');
});

it('provides translated labels for persisted enums', function (): void {
    expect(StructuredContentType::CaseStudy->getLabel())->toBe('Case study')
        ->and(StructuredContentStatus::Draft->getLabel())->toBe('Draft');
});
