<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems;

use BackedEnum;
use Capell\Admin\Support\SiteScope;
use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Enums\StructuredContentPayloadField;
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
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            Section::make(fn (?StructuredContentItem $record): string => $record === null
                ? __('capell-structured-content-library::admin.choose_type')
                : $record->type->getLabel())
                ->schema([
                    Radio::make('type')
                        ->label(__('capell-structured-content-library::admin.type'))
                        ->hiddenLabel()
                        ->view('capell-structured-content-library::forms.type-cards')
                        ->options(fn (?StructuredContentItem $record): array => $record === null
                            ? self::typeOptions()
                            : [$record->type->value => $record->type->getLabel()])
                        ->descriptions(self::typeDescriptions())
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->required()
                        ->live()
                        ->disabled(fn (?StructuredContentItem $record): bool => $record !== null)
                        ->dehydrated()
                        ->helperText(fn (?StructuredContentItem $record): string => __($record === null
                            ? 'capell-structured-content-library::admin.type_choice_help'
                            : 'capell-structured-content-library::validation.type_locked'))
                        ->afterStateUpdated(function (Set $set, ?StructuredContentItem $record): void {
                            if ($record === null) {
                                $set('payload', []);
                            }
                        }),
                ])
                ->collapsible()
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_common'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('capell-structured-content-library::admin.title'))
                        ->required()
                        ->maxLength(255),
                    Select::make('status')
                        ->label(__('capell-structured-content-library::admin.status'))
                        ->options(self::statusOptions())
                        ->required()
                        ->default(StructuredContentStatus::Draft->value),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) !== null)
                ->columns(2)
                ->columnSpanFull(),
            Section::make(fn (Get $get): string => ($type = self::payloadTypeFromState($get('type'))) === null
                ? ''
                : StructuredContentDefinitionData::forType($type)->groupLabel())
                ->schema(array_map(self::payloadField(...), StructuredContentPayloadField::cases()))
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) !== null)
                ->columns(2)
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_content'))
                ->schema([
                    Textarea::make('summary')
                        ->label(__('capell-structured-content-library::admin.summary'))
                        ->rows(3),
                    Textarea::make('content')
                        ->label(__('capell-structured-content-library::admin.content'))
                        ->rows(8)
                        ->helperText(__('capell-structured-content-library::admin.content_help')),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) !== null)
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
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) !== null)
                ->collapsed()
                ->columns(2)
                ->columnSpanFull(),
            Section::make(__('capell-structured-content-library::admin.section_publishing'))
                ->schema([
                    DateTimePicker::make('published_at')
                        ->label(__('capell-structured-content-library::admin.published_at')),
                ])
                ->visible(fn (Get $get): bool => self::payloadTypeFromState($get('type')) !== null)
                ->collapsed()
                ->columnSpanFull(),
            Hidden::make('site_id'),
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
