<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Policies\StructuredContentItemPolicy;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

beforeEach(function (): void {
    app()->register(FilamentShieldServiceProvider::class);

    Config::set('filament-shield.permissions.separator', ':');
    Config::set('filament-shield.permissions.case', 'pascal');
});

it('exposes a structured content Filament resource', function (): void {
    CapellCore::forcePackageInstalled(StructuredContentLibraryServiceProvider::$packageName);

    $pages = StructuredContentItemResource::getPages();

    expect(StructuredContentItemResource::getModel())->toBe(StructuredContentItem::class)
        ->and(StructuredContentItemResource::shouldRegisterNavigation())->toBeTrue()
        ->and(StructuredContentItemResource::getNavigationGroup())->toBe((string) __('capell-admin::navigation.group_content'))
        ->and(StructuredContentItemResource::getNavigationLabel())->toBe('Structured Content Items')
        ->and(StructuredContentItemResource::getModelLabel())->toBe('structured content item')
        ->and(StructuredContentItemResource::getPluralModelLabel())->toBe('structured content items')
        ->and(array_keys($pages))->toBe(['index', 'create', 'edit'])
        ->and($pages['index']->getPage())->toBe(ListStructuredContentItems::class)
        ->and($pages['create']->getPage())->toBe(CreateStructuredContentItem::class)
        ->and($pages['edit']->getPage())->toBe(EditStructuredContentItem::class);
});

it('scopes payload fields to the selected structured content type', function (): void {
    $coveredPayloadFields = collect(StructuredContentType::cases())
        ->flatMap(static fn (StructuredContentType $type): array => StructuredContentItemResource::payloadFieldsForType($type))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect(StructuredContentItemResource::payloadFieldsForType(StructuredContentType::Faq))->toBe([
        'question',
        'answer',
    ])
        ->and(StructuredContentItemResource::payloadFieldsForType(StructuredContentType::Testimonial))->toBe([
            'quote',
            'attribution',
            'role',
            'company',
            'image_alt',
        ])
        ->and(StructuredContentItemResource::payloadFieldsForType(StructuredContentType::Location))->toBe([
            'email',
            'phone',
            'street_address',
            'locality',
            'region',
            'postal_code',
            'country_code',
            'url',
        ])
        ->and($coveredPayloadFields)->toBe([
            'answer',
            'attribution',
            'company',
            'country_code',
            'email',
            'eyebrow',
            'image_alt',
            'locality',
            'logo_alt',
            'phone',
            'postal_code',
            'question',
            'quote',
            'region',
            'resource_kind',
            'role',
            'street_address',
            'subtitle',
            'url',
        ]);
});

it('denies structured content resources to ordinary panel users without permissions', function (): void {
    $user = structuredContentPolicyActor();
    $record = new StructuredContentItem(['site_id' => 10]);
    $policy = new StructuredContentItemPolicy;

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $record))->toBeFalse()
        ->and($policy->update($user, $record))->toBeFalse();
});

it('allows structured content resource access with matching shield permissions', function (): void {
    $user = structuredContentPolicyActor([
        structuredContentPolicyPermission('view_any'),
        structuredContentPolicyPermission('create'),
        structuredContentPolicyPermission('update'),
    ]);
    $record = new StructuredContentItem(['site_id' => 10]);
    $policy = new StructuredContentItemPolicy;

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $record))->toBeTrue()
        ->and($policy->update($user, $record))->toBeTrue();
});

it('blocks structured content record access outside assigned sites', function (): void {
    $user = structuredContentPolicyActor([
        structuredContentPolicyPermission('view_any'),
        structuredContentPolicyPermission('update'),
        structuredContentPolicyPermission('delete'),
    ], assignedSiteIds: collect([10]));
    $record = new StructuredContentItem(['site_id' => 20]);
    $policy = new StructuredContentItemPolicy;

    expect($policy->view($user, $record))->toBeFalse()
        ->and($policy->update($user, $record))->toBeFalse()
        ->and($policy->delete($user, $record))->toBeFalse();
});

it('registers the structured content item policy for the Filament resource model', function (): void {
    expect(Gate::getPolicyFor(StructuredContentItem::class))->toBeInstanceOf(StructuredContentItemPolicy::class);
});

/**
 * @param  list<string>  $permissions
 * @param  SupportCollection<int, int>|null  $assignedSiteIds
 */
function structuredContentPolicyActor(array $permissions = [], ?SupportCollection $assignedSiteIds = null): User
{
    $user = new class extends User
    {
        /** @use HasFactory<Factory<static>> */
        use HasFactory;

        /** @var list<string> */
        public array $permissions = [];

        /** @var SupportCollection<int, int> */
        public SupportCollection $assignedSiteIds;

        public function checkPermissionTo(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }

        /** @return SupportCollection<int, int> */
        public function getAssignedSiteIds(): SupportCollection
        {
            return $this->assignedSiteIds;
        }
    };

    $user->permissions = $permissions;
    $user->assignedSiteIds = $assignedSiteIds ?? collect([10]);

    return $user;
}

function structuredContentPolicyPermission(string $ability): string
{
    $ability = str($ability)
        ->replace('_', ' ')
        ->title()
        ->replace(' ', '')
        ->toString();

    return $ability . ':StructuredContentItem';
}
