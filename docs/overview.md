# Structured Content Library

<!-- prettier-ignore-start -->

## What it does

Structured Content Library stores reusable typed records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos. Themes and packages can consume the records as hydrated public data, but this package does not add a page, widget, route, or public template by itself.

Items are not translated records. A title, summary, body, and payload are stored once and can be reused wherever the item is selected. Create separate items, and configure the consuming feature accordingly, when different language content is required.

## Where to manage it

Go to **Content -> Structured Content Items**. The list shows title, type, status, publication date, and sort order. Search by title, filter by type/status/trash state, and drag items into their intended order.

The screen's Site field is hidden. A new item created there has global scope and can be returned for every Site. Site-specific items can be created through the package's typed integration/import action and are returned alongside global items only for that Site. The same slug can therefore exist in different Site scopes, but global and site-specific records are not automatically treated as overrides.

The admin list includes items assigned to the actor’s Sites and global items; global actors can see every Site. Create/update Actions enforce the actor’s Site scope. Keep global items under globally trusted roles.

## Create and publish an item

1. Select **Create structured content**.
2. Answer **What are you adding?** using one of the nine type cards. Each explains its purpose with an example.
3. Add the required **Title** and choose **Status** (initially **Draft**).
4. Complete the named type-specific group, then optional summary and content. Payload fields remain optional, including for title-only drafts.
5. Expand **Advanced** for a slug override or sort order, or **Publishing** for a publication date, then save.

Before the first save, choosing another type clears the unsaved type-specific fields and retains common content. After creation, the type is locked. Create a separate item for a different type: an existing record may already be selected by themes and imports under its original meaning. The update Action rejects type changes even from forged form state or stale model instances; there is no conversion operation.

The package-owned `StructuredContentDefinitionData` projects each type’s labels, description, example, named group and typed payload fields. `StructuredContentPayloadField` supplies requiredness, validation and public sanitisation. Create, update and import share the same payload validator, and public adapters use the same field membership. Non-empty incompatible fields are rejected on write, not silently carried into a new type. Historical incompatible fields are omitted from public output; editing and saving the current type removes hidden legacy fields.

Leaving the slug blank derives it from the title. Slugs are unique within the selected type and Site scope; a collision is suffixed rather than overwriting the existing item. Soft-deleted records continue to reserve their slugs, which keeps restore safe but means a replacement may receive `-2` or a later suffix.

Summary and content accept portable semantic HTML only: paragraphs, headings from `h2` to `h4`, lists, links, emphasis, blockquotes, code, and line breaks. Classes, styles, IDs, data/Alpine/Livewire/event attributes, embedded media, scripts, and other presentation markup are rejected on save or import. Put layout and visual styling in the consuming theme or widget.

Use **Preview saved content** on the edit page to inspect the saved values through the same hydrated public projection used by themes. It includes saved drafts, escapes all displayed text and does not publish anything or render your theme’s layout. Unsaved edits are excluded.

## Visibility and cache behaviour

Public adapters return only items whose status is **Published** and whose Published at value is empty/past. Publishing without a date records the current time. Future dates become visible from database queries when the time arrives; there is no scheduler, queue, or publishing retry job. Draft and Archived always remain out of public output even if they retain an earlier publication date.

Items are ordered by Sort order, then title and ID. Public results are cached separately by Site, locale, and requested types for five minutes by default. Saving, deleting, restoring, or force-deleting an item invalidates the package cache immediately; existing full-page or edge caches still follow the host application's own invalidation policy.

The public payload contains only title, slug, summary, content, and the type-specific fields. Payload values are reduced to plain text; invalid email values and unsafe URL schemes are omitted, while valid HTTP(S) and site-relative URLs are accepted. Internal model IDs, Site IDs, permissions, editor metadata, and package identifiers are not included.

## Deletion, permissions, and data handling

Delete moves an item to the trash and removes it from normal consumers. Restore is recoverable; force-delete is permanent. There is no automatic age-based retention or purge schedule. The relevant view, create, update, delete, restore, force-delete, replicate, and reorder actions remain subject to the resource policy and the actor's Site scope.

Integrators can use `ImportStructuredContentItemsAction` to create or update records by type, Site, and slug. Updates are enabled by default; disabling them skips matching items. The import is processed item by item rather than as one batch transaction, so records completed before a later validation or permission failure remain stored and should be reconciled from the returned counts/source data.

All titles, summaries, content, testimonial attribution, email, phone, and address payload fields are stored in plaintext. The package does not register a Privacy Center exporter or eraser. Avoid using it for subject-request data unless the owning feature supplies export, retention, correction, and erasure handling for these records and backups.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
