<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems;

use BackedEnum;
use Capell\Admin\Support\SiteScope;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Core\Support\Permissions\SiteAccess;
use Capell\StructuredContentLibrary\Actions\DescribeStructuredContentAvailabilityAction;
use Capell\StructuredContentLibrary\Actions\EnsurePortableContentHtmlAction;
use Capell\StructuredContentLibrary\Actions\EnsurePortableEditorDocumentAction;
use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Enums\StructuredContentPayloadField;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\StructuredContentLibrary\Support\RichEditor\PortableContentPlugin;
use Capell\StructuredContentLibrary\Support\RichEditor\PortableRichEditor;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;
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
            Section::make(fn (?StructuredContentItem $record): string => $record instanceof StructuredContentItem
                ? $record->type->getLabel()
                : __('capell-structured-content-library::admin.choose_type'))
                ->schema([
                    Radio::make('type')
                        ->label(__('capell-structured-content-library::admin.type'))
                        ->hiddenLabel()
                        ->view('capell-structured-content-library::forms.type-cards')
                        ->options(fn (?StructuredContentItem $record): array => $record instanceof StructuredContentItem
                            ? [$record->type->value => $record->type->getLabel()]
                            : self::typeOptions())
                        ->descriptions(self::typeDescriptions())
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->required()
                        ->live()
                        ->disabled(fn (?StructuredContentItem $record): bool => $record instanceof StructuredContentItem)
                        ->dehydrated()
                        ->helperText(fn (?StructuredContentItem $record): string => $record instanceof StructuredContentItem
                            ? __('capell-structured-content-library::validation.type_locked')
                            : __('capell-structured-content-library::admin.type_choice_help'))
                        ->afterStateUpdated(function (Set $set, ?StructuredContentItem $record): void {
                            if (! $record instanceof StructuredContentItem) {
                                $set('payload', []);
                            }
                        }),
                ])
                ->collapsible()
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_common'))
                ->schema([
                    TextEntry::make('saved_availability')
                        ->label(__('capell-structured-content-library::admin.saved_availability'))
                        ->state(fn (?StructuredContentItem $record): string => $record === null ? '' : DescribeStructuredContentAvailabilityAction::run($record))
                        ->visible(fn (?StructuredContentItem $record): bool => $record !== null),
                    TextInput::make('title')
                        ->label(__('capell-structured-content-library::admin.title'))
                        ->required()
                        ->maxLength(255),
                    Select::make('status')
                        ->label(__('capell-structured-content-library::admin.status'))
                        ->options(self::statusOptions())
                        ->required()
                        ->default(StructuredContentStatus::Draft->value),
                    Select::make('site_id')
                        ->label(__('capell-structured-content-library::admin.site'))
                        ->helperText(__('capell-structured-content-library::admin.site_help'))
                        ->options(self::siteOptions(...))
                        ->default(fn (): ?int => SiteAccess::current()->isGlobal()
                            ? null
                            : SiteAccess::current()->query(Site::class)->orderBy('name')->first()?->id)
                        ->afterStateHydrated(function (Select $component, ?StructuredContentItem $record): void {
                            if ($record instanceof StructuredContentItem && $record->site_id === null) {
                                $component->state('all');
                            }
                        })
                        ->dehydrateStateUsing(fn (int|string|null $state): ?int => $state === 'all' || $state === null ? null : (int) $state)
                        ->required(),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) instanceof StructuredContentType)
                ->columns(2)
                ->columnSpanFull(),
            Section::make(fn (Get $get): string => ($type = self::payloadTypeFromState($get('type'))) instanceof StructuredContentType
                ? StructuredContentDefinitionData::forType($type)->groupLabel()
                : '')
                ->schema(array_map(self::payloadField(...), StructuredContentPayloadField::cases()))
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) instanceof StructuredContentType)
                ->columns(2)
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_content'))
                ->schema([
                    self::portableEditor('summary'),
                    self::portableEditor('content'),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) instanceof StructuredContentType)
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_advanced'))
                ->schema([
                    TextInput::make('slug')
                        ->label(__('capell-structured-content-library::admin.slug'))
                        ->helperText(__('capell-structured-content-library::admin.slug_help'))
                        ->maxLength(255),
                    TextInput::make('sort_order')
                        ->label(__('capell-structured-content-library::admin.sort_order'))
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) instanceof StructuredContentType)
                ->collapsed()
                ->columns(2)
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_publishing'))
                ->schema([
                    DateTimePicker::make('published_at')
                        ->label(__('capell-structured-content-library::admin.published_at')),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) instanceof StructuredContentType)
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('capell-structured-content-library::admin.empty_heading'))
            ->emptyStateDescription(__('capell-structured-content-library::admin.empty_description'))
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
                TextColumn::make('site.name')
                    ->label(__('capell-structured-content-library::admin.site'))
                    ->placeholder(__('capell-structured-content-library::admin.all_sites')),
                TextColumn::make('status')
                    ->label(__('capell-structured-content-library::admin.status'))
                    ->formatStateUsing(fn (StructuredContentStatus|string|null $state): string => self::formatStatus($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('availability')
                    ->label(__('capell-structured-content-library::admin.availability'))
                    ->state(fn (StructuredContentItem $record): string => DescribeStructuredContentAvailabilityAction::run($record)),
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
                    DeleteAction::make()
                        ->modalHeading(__('capell-structured-content-library::structured_content_item.delete_modal.heading'))
                        ->modalDescription(__('capell-structured-content-library::structured_content_item.delete_modal.description')),
                    RestoreAction::make(),
                ])
                    ->color('gray'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->authorizeIndividualRecords('delete')
                    ->modalHeading(__('capell-structured-content-library::structured_content_item.delete_bulk_modal.heading'))
                    ->modalDescription(__('capell-structured-content-library::structured_content_item.delete_bulk_modal.description')),
                RestoreBulkAction::make()->authorizeIndividualRecords('restore'),
                ForceDeleteBulkAction::make()
                    ->authorizeIndividualRecords('forceDelete')
                    ->modalHeading(__('capell-structured-content-library::structured_content_item.force_delete_bulk_modal.heading'))
                    ->modalDescription(__('capell-structured-content-library::structured_content_item.force_delete_bulk_modal.description')),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return self::applySiteScope(
            parent::getEloquentQuery()->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]),
        );
    }

    #[Override]
    public static function getModel(): string
    {
        return StructuredContentItem::class;
    }

    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return (string) __('capell-admin::navigation.group_content');
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
        return StructuredContentDefinitionData::forType($type)->fieldNames();
    }

    private static function portableEditor(string $name): RichEditor
    {
        return PortableRichEditor::make($name)
            ->plugins([new PortableContentPlugin])
            ->label(__('capell-structured-content-library::admin.' . $name))
            ->helperText(__('capell-structured-content-library::admin.content_help'))
            ->toolbarButtons([
                ['paragraph', 'h2', 'h3', 'h4'],
                ['bold', 'italic', 'link'],
                ['bulletList', 'orderedList', 'blockquote', 'code', 'codeBlock'],
                ['undo', 'redo'],
            ])
            ->floatingToolbars([])
            ->fileAttachments(false)
            ->dehydrateStateUsing(fn (?string $state): ?string => in_array($state, ['', '<p></p>'], true) ? null : $state)
            ->rules([
                fn (RichEditor $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                    try {
                        EnsurePortableEditorDocumentAction::run($component->getRawState());
                        $html = $component->getState();

                        if (! is_string($html)) {
                            $fail(__('capell-structured-content-library::validation.portable_content'));

                            return;
                        }

                        EnsurePortableContentHtmlAction::run($html);
                    } catch (ValidationException) {
                        $fail(__('capell-structured-content-library::validation.portable_content'));
                    }
                },
            ]);
    }

    /** @return array<int|string, string> */
    private static function siteOptions(): array
    {
        $access = SiteAccess::current();
        $sites = [];

        foreach ($access->query(Site::class)->orderBy('name')->get() as $site) {
            $sites[$site->id] = $site->name;
        }

        return $access->isGlobal()
            ? ['all' => __('capell-structured-content-library::admin.all_sites')] + $sites
            : $sites;
    }

    private static function payloadField(StructuredContentPayloadField $definition): Field
    {
        $field = $definition->isMultiline()
            ? Textarea::make('payload.' . $definition->value)->rows(3)
            : TextInput::make('payload.' . $definition->value);

        return $field
            ->label($definition->label())
            ->required($definition->isRequired())
            ->rules(fn (): array => $definition->rules())
            ->visible(fn (Get $get): bool => self::isPayloadFieldVisible($get, $definition->value));
    }

    /** @return array<string, string> */
    private static function typeDescriptions(): array
    {
        $descriptions = [];

        foreach (StructuredContentType::cases() as $type) {
            $definition = StructuredContentDefinitionData::forType($type);
            $descriptions[$type->value] = $definition->description() . ' ' . $definition->example();
        }

        return $descriptions;
    }

    /**
     * StructuredContentItem carries a nullable site_id (null means visible
     * portfolio-wide, mirroring the model's own visibleToSite() scope and
     * the policy's canUseRecordSite()). Without this, the admin table lists
     * every site's items to any actor with view_any, regardless of which
     * site(s) they are assigned to.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private static function applySiteScope(Builder $query): Builder
    {
        $actor = auth()->user();

        if (! $actor instanceof Authenticatable || SiteScope::isGlobalActor($actor)) {
            return $query;
        }

        $assignedSiteIds = $actor->getAssignedSiteIds();

        return $query->where(function (Builder $query) use ($assignedSiteIds): void {
            $query->whereNull('site_id');

            if ($assignedSiteIds->isNotEmpty()) {
                $query->orWhereIn('site_id', $assignedSiteIds);
            }
        });
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
