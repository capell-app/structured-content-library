## What it does

Structured Content Library stores reusable typed records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos. Themes and packages can consume the records as hydrated public data, but this package does not add a page, widget, route, or public template by itself.

Items are not translated records. A title, summary, body, and payload are stored once and can be reused wherever the item is selected. Create separate items, and configure the consuming feature accordingly, when different language content is required.

## Where to manage it

Go to **Content -> Structured Content Items**. The list shows title, type, status, publication date, and sort order. Search by title, filter by type/status/trash state, and drag items into their intended order.

The screen's Site field is hidden. A new item created there has global scope and can be returned for every Site. Site-specific items can be created through the package's typed integration/import action and are returned alongside global items only for that Site. The same slug can therefore exist in different Site scopes, but global and site-specific records are not automatically treated as overrides.

The admin list itself is installation-wide rather than automatically filtered to assigned Sites, so titles and statuses can be visible to any user with list access. Create/update actions prevent a site-scoped actor from writing another Site's scope or global scope. However, the record policy treats a global item as usable by any actor who has the corresponding delete/restore/force-delete permission. Keep global items under globally trusted roles and do not grant those destructive permissions to site-only roles when global items are present.

## Create and publish an item

1. Select **Create structured content item**.
2. Choose the type and status. New items default to **Draft**.
3. Add the required title, then complete the optional slug, summary, and content.
4. Complete the fields shown for that type, such as quote and attribution for a testimonial, question and answer for an FAQ, or address/contact fields for a location.
5. Set **Published at** and **Sort order** when needed, then save.

Leaving the slug blank derives it from the title. Slugs are unique within the selected type and Site scope; a collision is suffixed rather than overwriting the existing item. Soft-deleted records continue to reserve their slugs, which keeps restore safe but means a replacement may receive `-2` or a later suffix.

Summary and content accept portable semantic HTML only: paragraphs, headings from `h2` to `h4`, lists, links, emphasis, blockquotes, code, and line breaks. Classes, styles, IDs, data/Alpine/Livewire/event attributes, embedded media, scripts, and other presentation markup are rejected on save or import. Put layout and visual styling in the consuming theme or widget.

## Visibility and cache behaviour

Public adapters return only items whose status is **Published** and whose Published at value is empty/past. Publishing without a date records the current time. Future dates become visible from database queries when the time arrives; there is no scheduler, queue, or publishing retry job. Draft and Archived always remain out of public output even if they retain an earlier publication date.

Items are ordered by Sort order, then title and ID. Public results are cached separately by Site, locale, and requested types for five minutes by default. Saving, deleting, restoring, or force-deleting an item invalidates the package cache immediately; existing full-page or edge caches still follow the host application's own invalidation policy.

The public payload contains only title, slug, summary, content, and the type-specific fields. Payload values are reduced to plain text; invalid email values and unsafe URL schemes are omitted, while valid HTTP(S) and site-relative URLs are accepted. Internal model IDs, Site IDs, permissions, editor metadata, and package identifiers are not included.

## Deletion, permissions, and data handling

Delete moves an item to the trash and removes it from normal consumers. Restore is recoverable; force-delete is permanent. There is no automatic age-based retention or purge schedule. The relevant view, create, update, delete, restore, force-delete, replicate, and reorder actions remain subject to the resource policy and the actor's Site scope.

Integrators can use `ImportStructuredContentItemsAction` to create or update records by type, Site, and slug. Updates are enabled by default; disabling them skips matching items. The import is processed item by item rather than as one batch transaction, so records completed before a later validation or permission failure remain stored and should be reconciled from the returned counts/source data.

All titles, summaries, content, testimonial attribution, email, phone, and address payload fields are stored in plaintext. The package does not register a Privacy Center exporter or eraser. Avoid using it for subject-request data unless the owning feature supplies export, retention, correction, and erasure handling for these records and backups.
