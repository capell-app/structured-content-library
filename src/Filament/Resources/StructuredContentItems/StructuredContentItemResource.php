<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems;

use BackedEnum;
use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;

class StructuredContentItemResource extends Resource
{
    protected static ?string $slug = 'structured-content-library/structured-content-items';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    #[Override]
    public static function form(Schema $configurator): Schema
    {
        return $configurator->components([
            Section::make(__('capell-structured-content-library::admin.section_content'))
                ->schema([
                    Select::make('type')
                        ->label(__('capell-structured-content-library::admin.type'))
                        ->options(self::typeOptions())
                        ->required(),
                    Select::make('status')
                        ->label(__('capell-structured-content-library::admin.status'))
                        ->options(self::statusOptions())
                        ->required()
                        ->default(StructuredContentStatus::Draft->value),
                    TextInput::make('title')
                        ->label(__('capell-structured-content-library::admin.title'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->label(__('capell-structured-content-library::admin.slug'))
                        ->maxLength(255),
                    Textarea::make('summary')
                        ->label(__('capell-structured-content-library::admin.summary'))
                        ->rows(3),
                    Textarea::make('content')
                        ->label(__('capell-structured-content-library::admin.content'))
                        ->rows(8)
                        ->helperText(__('capell-structured-content-library::admin.content_help')),
                ])
                ->columns(2),
            Section::make(__('capell-structured-content-library::admin.section_metadata'))
                ->schema([
                    DateTimePicker::make('published_at')
                        ->label(__('capell-structured-content-library::admin.published_at')),
                    TextInput::make('sort_order')
                        ->label(__('capell-structured-content-library::admin.sort_order'))
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    Hidden::make('site_id'),
                ])
                ->columns(2),
            Section::make(__('capell-structured-content-library::admin.section_payload'))
                ->schema([
                    TextInput::make('payload.eyebrow')
                        ->label(__('capell-structured-content-library::admin.payload_eyebrow'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'eyebrow')),
                    TextInput::make('payload.subtitle')
                        ->label(__('capell-structured-content-library::admin.payload_subtitle'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'subtitle')),
                    Textarea::make('payload.quote')
                        ->label(__('capell-structured-content-library::admin.payload_quote'))
                        ->rows(3)
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'quote')),
                    TextInput::make('payload.attribution')
                        ->label(__('capell-structured-content-library::admin.payload_attribution'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'attribution')),
                    TextInput::make('payload.role')
                        ->label(__('capell-structured-content-library::admin.payload_role'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'role')),
                    TextInput::make('payload.company')
                        ->label(__('capell-structured-content-library::admin.payload_company'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'company')),
                    TextInput::make('payload.question')
                        ->label(__('capell-structured-content-library::admin.payload_question'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'question')),
                    Textarea::make('payload.answer')
                        ->label(__('capell-structured-content-library::admin.payload_answer'))
                        ->rows(3)
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'answer')),
                    TextInput::make('payload.resource_kind')
                        ->label(__('capell-structured-content-library::admin.payload_resource_kind'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'resource_kind')),
                    TextInput::make('payload.url')
                        ->label(__('capell-structured-content-library::admin.payload_url'))
                        ->url()
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'url')),
                    TextInput::make('payload.email')
                        ->label(__('capell-structured-content-library::admin.payload_email'))
                        ->email()
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'email')),
                    TextInput::make('payload.phone')
                        ->label(__('capell-structured-content-library::admin.payload_phone'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'phone')),
                    TextInput::make('payload.street_address')
                        ->label(__('capell-structured-content-library::admin.payload_street_address'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'street_address')),
                    TextInput::make('payload.locality')
                        ->label(__('capell-structured-content-library::admin.payload_locality'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'locality')),
                    TextInput::make('payload.region')
                        ->label(__('capell-structured-content-library::admin.payload_region'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'region')),
                    TextInput::make('payload.postal_code')
                        ->label(__('capell-structured-content-library::admin.payload_postal_code'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'postal_code')),
                    TextInput::make('payload.country_code')
                        ->label(__('capell-structured-content-library::admin.payload_country_code'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'country_code')),
                    TextInput::make('payload.image_alt')
                        ->label(__('capell-structured-content-library::admin.payload_image_alt'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'image_alt')),
                    TextInput::make('payload.logo_alt')
                        ->label(__('capell-structured-content-library::admin.payload_logo_alt'))
                        ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, 'logo_alt')),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) instanceof StructuredContentType)
                ->columns(2),
        ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('capell-structured-content-library::admin.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('capell-structured-content-library::admin.type'))
                    ->formatStateUsing(fn (StructuredContentType|string|null $state): string => self::formatType($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('capell-structured-content-library::admin.status'))
                    ->formatStateUsing(fn (StructuredContentStatus|string|null $state): string => self::formatStatus($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label(__('capell-structured-content-library::admin.published_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('capell-structured-content-library::admin.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('capell-structured-content-library::admin.type'))
                    ->options(self::typeOptions()),
                SelectFilter::make('status')
                    ->label(__('capell-structured-content-library::admin.status'))
                    ->options(self::statusOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make(),
                    RestoreAction::make(),
                ])
                    ->color('gray'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    #[Override]
    public static function getModel(): string
    {
        return StructuredContentItem::class;
    }

    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return __('capell-structured-content-library::admin.navigation_group');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('capell-structured-content-library::admin.navigation_label');
    }

    #[Override]
    public static function shouldRegisterNavigation(): bool
    {
        return CapellCore::isPackageInstalled(StructuredContentLibraryServiceProvider::$packageName);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('capell-structured-content-library::admin.model_label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('capell-structured-content-library::admin.plural_model_label');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListStructuredContentItems::route('/'),
            'create' => CreateStructuredContentItem::route('/create'),
            'edit' => EditStructuredContentItem::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function payloadFieldsForType(StructuredContentType $type): array
    {
        return match ($type) {
            StructuredContentType::CaseStudy => [
                'eyebrow',
                'subtitle',
                'company',
                'url',
                'image_alt',
            ],
            StructuredContentType::Testimonial => [
                'quote',
                'attribution',
                'role',
                'company',
                'image_alt',
            ],
            StructuredContentType::TeamMember => [
                'subtitle',
                'role',
                'company',
                'email',
                'phone',
                'url',
                'image_alt',
            ],
            StructuredContentType::Service => [
                'eyebrow',
                'subtitle',
                'url',
                'image_alt',
            ],
            StructuredContentType::Faq => [
                'question',
                'answer',
            ],
            StructuredContentType::Resource => [
                'resource_kind',
                'url',
                'image_alt',
            ],
            StructuredContentType::Partner => [
                'company',
                'url',
                'logo_alt',
            ],
            StructuredContentType::Location => [
                'email',
                'phone',
                'street_address',
                'locality',
                'region',
                'postal_code',
                'country_code',
                'url',
            ],
            StructuredContentType::Logo => [
                'company',
                'url',
                'logo_alt',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        $options = [];

        foreach (StructuredContentType::cases() as $type) {
            $options[$type->value] = $type->getLabel();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (StructuredContentStatus::cases() as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }

    private static function isPayloadFieldVisible(Get $get, string $field): bool
    {
        $type = self::payloadTypeFromState($get('type'));

        return $type instanceof StructuredContentType
            && in_array($field, self::payloadFieldsForType($type), true);
    }

    private static function payloadTypeFromState(mixed $state): ?StructuredContentType
    {
        if ($state instanceof StructuredContentType) {
            return $state;
        }

        return is_string($state) ? StructuredContentType::tryFrom($state) : null;
    }

    private static function formatType(StructuredContentType|string|null $state): string
    {
        if ($state instanceof StructuredContentType) {
            return $state->getLabel();
        }

        if (is_string($state) && ($type = StructuredContentType::tryFrom($state)) instanceof StructuredContentType) {
            return $type->getLabel();
        }

        return '';
    }

    private static function formatStatus(StructuredContentStatus|string|null $state): string
    {
        if ($state instanceof StructuredContentStatus) {
            return $state->getLabel();
        }

        if (is_string($state) && ($status = StructuredContentStatus::tryFrom($state)) instanceof StructuredContentStatus) {
            return $status->getLabel();
        }

        return '';
    }
}
