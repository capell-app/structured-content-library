# Structured Content Library — Improvement & Growth Plan

> Package: capell-app/structured-content-library · Kind: package · Tier: free · Product group: Capell Foundation · Bundle: foundation · Status: Draft

## 1. Snapshot

Structured Content Library provides one package-owned Eloquent model (`StructuredContentItem`, table `structured_content_items` — `src/Models/StructuredContentItem.php`) for nine fixed reusable business-content concepts driven by `StructuredContentType` (case study, testimonial, team member, service, FAQ, resource, partner, location, logo — `src/Enums/StructuredContentType.php`). Content is stored as a flat row (`title`, `slug`, `summary`, `content`) plus a single typed `payload` JSON cast to `StructuredContentPayloadData` (19 optional scalar fields — `src/Data/StructuredContentPayloadData.php`). Surfaces are `admin` (one Filament resource, `src/Filament/Resources/StructuredContentItems/StructuredContentItemResource.php`) and `shared`; there is **no** frontend surface (`providers.frontend: []`). Key Actions: `CreateStructuredContentItemAction`, `UpdateStructuredContentItemAction`, `ListStructuredContentItemsAction`, `BuildPublicStructuredContentItemsAction`, `BuildStructuredContentSectionsAction`, `ImportStructuredContentItemsAction`, and the HTML guard `EnsurePortableContentHtmlAction`. Deps: `capell-app/admin`, `capell-app/core`, `filament/support`, `lorisleiva/laravel-actions`, `spatie/laravel-data`, `spatie/laravel-package-tools` (`composer.json`). Marketplace summary verbatim: **"Portable reusable content records for theme-rendered business content."** Screenshot count: **1** (`docs/assets/marketplace/extension-card.jpg`) — a single generic "extension preview" card; no admin/list/form/payload screenshots (mismatch vs. an admin-surface package that should show its UI).

## 2. Improvements (existing functionality)

- **Done/Shipped: Add a unique index on `(type, site_id, slug)`.** The package migration now repairs duplicate scoped non-null slugs before creating `structured_content_type_site_slug_unique`, and import coverage proves generated slugs are used for dedup when incoming rows omit `slug`. — data integrity — `database/migrations/2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table.php`, `tests/Integration/Actions/ImportStructuredContentItemsActionTest.php`, `tests/Integration/Models/StructuredContentItemTest.php` — **S**

- **Done/Shipped: Implemented the health check.** `StructuredContentLibraryHealthCheck` now probes the storage table, `CapellCore` model registration, protected-table registration, and admin resource contribution with translated labels/messages/remediations. Focused health coverage pins the probe set and failure messages. — manifest/behaviour alignment, diagnostics value — `src/Health/StructuredContentLibraryHealthCheck.php`, `resources/lang/en/health.php`, `tests/Integration/Health/StructuredContentLibraryHealthCheckTest.php` — **S**

- **Done/Shipped: Auto-derive `published_at` when publishing without a date.** Create defaults `published_at` for new published records, and update now defaults to `now()` only when transitioning to `Published` with no supplied date while preserving existing timestamps on published and non-publish updates. Evidence: `UpdateStructuredContentItemActionTest` covers the publish transition, preserving an existing timestamp, and no timestamp change for non-publish transitions. — correctness/UX — `src/Actions/CreateStructuredContentItemAction.php`, `src/Actions/UpdateStructuredContentItemAction.php` — **S**

- **Done/Shipped: Sanitize `summary` through the portable-HTML guard.** Create and update actions now pass `summary` through `EnsurePortableContentHtmlAction` with field-specific validation errors, matching the `content` portability contract before values reach public DTOs. Evidence: `CreateStructuredContentItemActionTest` and `UpdateStructuredContentItemActionTest` persist safe portable summary HTML and reject script tags plus inline event handlers without storing unsafe values. — public-output safety + contract consistency — `src/Actions/CreateStructuredContentItemAction.php`, `src/Actions/UpdateStructuredContentItemAction.php`, `src/Actions/EnsurePortableContentHtmlAction.php` — **S**

- **Make the `payload` form fields type-aware.** The Filament form renders all 19 payload fields unconditionally for every type, so editing a "Logo" shows `quote`, `question`, `postal_code`, etc. Use `Get $get('type')` + `->visible()` to scope payload fields per content type. — admin UX, reduces data-entry error — `src/Filament/Resources/StructuredContentItems/StructuredContentItemResource.php` — **M**

- **Add an `archived()`/trashed table filter and surface soft-deletes.** `getEloquentQuery()` strips `SoftDeletingScope` so trashed rows appear in the list, but there is no Trashed filter and no restore/force-delete actions, and no `DeleteAction`/`RestoreAction` are registered on the pages. Either re-scope and add a `TrashedFilter`, or add restore actions. — admin UX, data safety — `src/Filament/Resources/StructuredContentItems/StructuredContentItemResource.php`, `.../Pages/ListStructuredContentItems.php` — **S**

- **Add slug-uniqueness handling in write actions.** `Str::slug($title)` with no collision suffix means two items of the same type/site with the same title produce identical slugs. Add a uniquing strategy (suffix `-2`, scoped to type+site). — data integrity, theme URL stability — `src/Actions/CreateStructuredContentItemAction.php`, `src/Actions/UpdateStructuredContentItemAction.php` — **M**

- **Expose `sort_order` / `published_at` editing in the table (drag-reorder or inline).** Ordering is the primary theme-facing contract (`scopeOrdered`) but reordering requires opening each record. Add Filament `->reorderable('sort_order')`. — admin UX — `src/Filament/Resources/StructuredContentItems/StructuredContentItemResource.php` — **S**

## 3. Missing Features (gaps)

Manifest `capabilities[]`: `structured-content-library`, `structured-content-public-adapter`, `structured-content-section-adapter`, `structured-content-theme-adapter`, `structured-content-import`.

- **Done/Shipped: Section/theme adapter capabilities are no longer advertised as delivered.** The unwired `structured-content-section-adapter` and `structured-content-theme-adapter` capability strings were removed from `capell.json`, while `content-section-adapter` and `theme-adapter` are now listed under `contributionTraceability.deferredContributions`. `StructuredContentLibraryProviderTest` preserves the shipped in-process `BuildStructuredContentSectionsAction` contract and asserts the adapter claims remain deferred until real integrations exist. — `capell.json` (`capabilities`, `contributionTraceability`), `tests/Unit/Providers/StructuredContentLibraryProviderTest.php`

- **Custom / extensible content types (differentiator).** `StructuredContentType` is a hard-coded 9-case enum. A structured-content library's headline value vs. WordPress is _user-defined_ content types. There is no registry (`CapellCore::registerStructuredContentType(...)`-style) for downstream packages/themes to add a type. This is the single biggest growth lever. — `src/Enums/StructuredContentType.php`

- **Repeatable / nested field groups (table-stakes for structured content).** `payload` is a single flat DTO of scalars. There is no support for repeatable groups (e.g. a service with N feature bullets, a team member with N social links, an FAQ _set_). Today this forces one row per item with no grouping primitive. — `src/Data/StructuredContentPayloadData.php`

- **Relations / references between items (differentiator).** No way to reference one item from another (e.g. testimonial → team member, case study → service). No `references` column, morph, or relation beyond `site`. — `src/Models/StructuredContentItem.php`

- **Media / image handling (table-stakes).** Payload carries only `image_alt` / `logo_alt` strings — no image URL, asset id, or media-library integration. "Logo" and "team member" types are effectively text-only. — `src/Data/StructuredContentPayloadData.php`

- **Per-field validation beyond HTML portability (table-stakes).** Only `title` (required) and `content`/`summary` (portability) are validated in actions. `url`/`email`/`phone`/`country_code` payload fields have Filament-level `->url()`/`->email()` hints but **no** action-level validation, so imports and programmatic writes accept anything. — `src/Actions/CreateStructuredContentItemAction.php`, `src/Data/StructuredContentPayloadData.php`

- **Read API exposure (gap given the "portable" pitch).** `composer.json` requires `spatie/laravel-data` and the public DTO exists, but there is no JSON/REST endpoint or API resource (`grep` finds no `JsonResource`/`ApiResource`). "Portable" currently means "in-process Action only". A signed/public read endpoint would make the package genuinely portable to JS themes and headless consumers. — package-wide

- **Versioning / revision history (differentiator).** No revisions, no draft-vs-published divergence, no audit trail. Updates overwrite in place. Sibling packages (e.g. KnowledgeBase article versions) model this. — `src/Models/StructuredContentItem.php`

- **Export counterpart to import.** `ImportStructuredContentItemsAction` exists with no `ExportStructuredContentItemsAction`, undercutting the "portable/migration tool" positioning and round-trip demo-kit story. — `src/Actions/`

- **Localised content (i18n gap).** Manifest declares `cacheSafety.variesBy: ["site","locale"]`, but the model has no per-locale content columns or translation strategy — only a `site_id`. Cached output varying by locale would serve identical text across locales. — `src/Models/StructuredContentItem.php`, `capell.json`

## 4. Issues / Risks

- **`payload` is exposed publicly as an unsanitised raw array.** `BuildPublicStructuredContentItemsAction` emits `payload: $item->payload?->toArray()` with no escaping/whitelisting. `EnsurePortableContentHtmlAction` never touches payload values, so `quote`, `answer`, `subtitle`, `url`, etc. pass through verbatim. If a theme echoes payload fields without `{{ }}` escaping, this is an XSS vector. Public-safety responsibility is silently delegated to every theme. — `src/Actions/BuildPublicStructuredContentItemsAction.php`

- **Done/Shipped: Critical health check is real.** Diagnostics now fail on missing storage table, missing Core model registration, missing protected-table registration, or missing admin resource contribution. — `src/Health/StructuredContentLibraryHealthCheck.php`, `tests/Integration/Health/StructuredContentLibraryHealthCheckTest.php`

- **Done/Shipped: unique constraint and null-slug import dedup are covered.** Existing duplicate scoped non-null slugs are repaired before the unique index is added, and imports without an explicit `slug` deduplicate against the generated slug. Note: direct future inserts with `site_id = NULL` may still depend on database-specific nullable unique-index semantics. — `database/migrations/2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table.php`, `tests/Integration/Actions/ImportStructuredContentItemsActionTest.php`

- **Cache safety is declared but unimplemented in this package.** `capell.json` sets `cacheable: true`, `cacheTags: ["structured-content-library"]`, `queueInvalidation: true`, and invalidation on item created/updated/deleted — but there is no `CacheInvalidationRegistry::registerDependency(...)` call in `src/` and no caching in the build/list actions. Every `BuildPublic`/`BuildSections` call hits the DB. The manifest describes intended behaviour the code does not yet provide. — `capell.json`, `src/Actions/ListStructuredContentItemsAction.php`, `src/Providers/StructuredContentLibraryServiceProvider.php`

- **Performance budget vs. reality.** Manifest `adminQueryBudget: 20`, `frontendRenderBudgetMs: 10`. `BuildStructuredContentSectionsAction` issues one query per section (calls `BuildPublic` → `List` → `->get()` in a loop), so an N-section theme page = N queries with no eager batching or cache. No test or benchmark asserts the budget. — `src/Actions/BuildStructuredContentSectionsAction.php`, `capell.json`

- **Test gaps.** 39 package tests pass in the current structured-content-library slice. Covered: data mapping, enum labels, provider/manifest declarations, resource page wiring, CRUD actions, portable-HTML rejection (create + update), summary portable-HTML validation, list ordering/site filtering, build-public + limit, build-sections, import create/update/skip, model casts/table install. **Not covered:** the Filament pages' `handleRecordCreation`/`handleRecordUpdate` Action delegation (no Livewire form test → admin save path untested end-to-end); `archived()`/`draft()` scopes; soft-delete visibility behaviour; payload XSS/escaping assertions; `published_at`-in-future exclusion edge; import with null slug. No arch test asserting public-DTO field whitelist. — `tests/`

- **`payload` form fields use `dehydrated` defaults implicitly.** All payload sub-fields are always-present `TextInput`/`Textarea`; an empty payload still serialises 19 null keys into `StructuredContentPayloadData`. Harmless but bloats stored JSON and public output. — `src/Filament/Resources/.../StructuredContentItemResource.php`

- **i18n: only `en` translations.** `resources/lang/en/{admin,status,type,validation}.php` only. All labels are translation-keyed (good), but no other locale ships, and content itself is single-locale (see §3). — `resources/lang/`

## 5. Marketplace & Positioning

This is a **free / foundation / bundled** package (`product.tier: free`, `bundle: foundation`, `commercial.proposedLicense: free`, `requestedCertification: first-party`). Its strategic role is to be the typed-content substrate other paid packages and themes consume — so its value is measured by adoption depth, not standalone revenue.

- **`marketplace.summary` critique.** Current: _"Portable reusable content records for theme-rendered business content."_ — abstract, jargon-led ("records", "theme-rendered"), gives a browsing user no concrete picture. Improved: _"A typed content library for testimonials, case studies, team members, FAQs, services and more — reusable, theme-safe records your themes render anywhere."_ Naming the concrete types is the hook; "theme-safe" signals the portability guard that differentiates it from a free-text block.

- **`composer.json` description critique.** Current: _"Reusable structured content records for Capell themes and packages."_ Acceptable but interchangeable with the manifest `description`. Tighten and align the two (manifest `description` lists all nine types; composer should match the shorter marketing line). Improved composer description: _"Typed, portable content records (testimonials, case studies, team, FAQs, services…) that Capell themes and packages render safely."_

- **free/bundle vs premium.** Correctly free/foundation — it should be the gravity well that makes premium packages (content-sections, themes, automation) more valuable. Keep the model/Actions free; the _premium_ upsell surface is layered features: revisions, API exposure, custom-type registry, media handling (§3). Do not paywall the core read Actions — that would break the foundation role.

- **Screenshot / media gaps.** Only one generic extension card. For an admin-surface package, add: (1) the list table with type/status badges, (2) the create form showing the type + payload sections, (3) a theme rendering published items (proves the "theme renders the data" story). The marketplace currently can't show the product actually working.

- **Platform-pitch contribution.** Structured/typed content is a top differentiator vs. WordPress (which leans on free-text blocks + plugins for custom types). This package is the credibility anchor for that pitch — but only once **custom content types** (§3) and a **wired section/theme adapter** (§3) exist. Today the pitch is partly aspirational: the adapters are manifest strings, and the type set is fixed.

- **Keywords / tags (8–12):** `structured-content`, `typed-content`, `content-modeling`, `reusable-content`, `testimonials`, `case-studies`, `faqs`, `team-members`, `headless-content`, `cms-foundation`, `theme-content`, `portable-content`.

## 6. Prioritized Roadmap

| Item                                                                      | Bucket | Effort | Impact                      | Section ref |
| ------------------------------------------------------------------------- | ------ | ------ | --------------------------- | ----------- |
| Done/Shipped: Sanitize `summary` via portable-HTML guard. Evidence: `CreateStructuredContentItemActionTest` and `UpdateStructuredContentItemActionTest` persist safe portable summary HTML and reject script tags plus inline event handlers. | Done | S | High (public safety) | §2 |
| Done/Shipped: Implement real health check (drop stub). Evidence: storage table, Core model, protected table, and admin resource diagnostics are translated and covered. | Done | S | High (false-green critical) | §2, §4 |
| Done/Shipped: Add unique index on `(type, site_id, slug)` + fix null-slug import dedup. Evidence: migration deduplicates legacy scoped slugs before adding the unique index, and import tests prove omitted slugs still deduplicate through the generated slug. | Done | S | High (data integrity) | §2, §4 |
| Done/Shipped: Wire section/theme adapter OR mark capabilities deferred. Evidence: `capell.json` removes unwired adapter capabilities, marks adapter contributions deferred, and `StructuredContentLibraryProviderTest` asserts the truthful manifest contract. | Done | M | High (manifest honesty) | §3 |
| Document/assert payload public-output escaping contract (+ arch/XSS test) | Now    | S      | High (public safety)        | §4          |
| Done/Shipped: Default `published_at` to now() on publish transition. Evidence: `UpdateStructuredContentItemActionTest` covers the publish transition, preserving an existing timestamp, and no timestamp change for non-publish transitions. | Done | S | Med (correctness) | §2 |
| Add Filament test for admin save path (Action delegation)                 | Next   | S      | Med (test gap)              | §4          |
| Type-aware payload form fields (`->visible()` by type)                    | Next   | M      | Med (admin UX)              | §2          |
| Trashed filter + restore/delete actions + reorderable table               | Next   | S      | Med (admin UX/safety)       | §2          |
| Slug collision uniquing in write actions                                  | Next   | M      | Med (URL stability)         | §2          |
| Implement declared caching + invalidation registry; benchmark budget      | Next   | M      | Med (perf, manifest match)  | §4          |
| Custom/extensible content-type registry                                   | Later  | L      | High (differentiator)       | §3, §5      |
| Repeatable/nested payload field groups                                    | Later  | L      | High (differentiator)       | §3          |
| Read API (public JSON resource/endpoint)                                  | Later  | M      | Med (portability story)     | §3, §5      |
| Revisions/versioning + Export action; media handling; i18n content        | Later  | L      | Med (premium upsell)        | §3, §5      |
| Marketplace: add 3 real screenshots + rewrite summary/description         | Now    | S      | Med (positioning)           | §5          |
