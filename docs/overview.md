# Structured Content Library

<!-- prettier-ignore-start -->

Your editors can keep content that turns up on several pages, such as a testimonial or a team profile, as a single record and edit it once. Structured Content Items sits in the Website group of the admin menu and holds nine kinds of record, such as testimonials, FAQs, team members and locations. Each kind has its own fields.

Records show on a page once a developer has added them to your theme or a section. A record can carry a translation for each site language, and records with a picture or logo show it with its alt text.

Every record starts as a draft. Publish it now, or publish it with a later date and it becomes available from then. Give it an end date and it leaves the site at that moment. Each published change is kept in the revision history when Publishing Studio is installed, and you can restore an earlier version. A record belongs to one site or to all of them. Global administrators change shared records, while an editor limited to one site changes only that site's. Drafts and archived records never reach the site, and the Preview saved content button shows you the saved values.

## What it does

Structured Content Library stores reusable typed records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos. Themes and packages can consume the records as hydrated public data, but this package does not add a page, widget, route, or public template by itself.

Each item can hold a translation per site language: a title, summary, body and payload that replace the item's own wording on pages in that language. A page in a language without a translation falls back through the site's language chain, then to the item's own wording. Testimonials, team members and resources take an image, and partners and logos take a logo, stored as Capell media; public data carries the file's URL, width, height and alt text, never media IDs.

## Where to manage it

Go to **Content -> Structured Content Items**. The list shows title, type, Site, status, publication date, and sort order. Search by title, filter by type/status/trash state, and review items in their intended order. Global administrators can drag items to reorder the mixed Site/global list; Site editors can set Sort order in an authorised item's Advanced section.

Choose **Site** to control where an item can be reused. Site editors see only their assigned Sites, with the first available Site selected initially. Global administrators can choose any Site or explicitly select **All sites** to share the item across the installation. Existing items retain their saved scope when reopened. Site-specific items are returned alongside global items only for that Site. The same slug can therefore exist in different Site scopes, but global and site-specific records are not automatically treated as overrides.

The admin list includes items assigned to the actor's Sites and global items; global actors can see every Site. Create/update Actions enforce the actor's Site scope. Only global administrators can edit, delete, restore or force-delete shared items. Bulk actions check each selected record's policy; Site editors retain read access to shared items.

## Create and publish an item

1. Select **Create structured content**.
2. Answer **What are you adding?** using one of the nine type cards. Each explains its purpose with an example.
3. Add the required **Title**, choose **Status** (initially **Draft**) and confirm **Site**. Global administrators must choose a Site or **All sites** before saving.
4. Complete the named type-specific group, then optional summary and content using the formatting controls for headings, lists, links, emphasis, quotes and code. Payload fields remain optional, including for title-only drafts.
5. Expand **Advanced** for a key override or sort order, or **Publishing** for a publication date, then save.

Before the first save, choosing another type clears the unsaved type-specific fields and retains common content. After creation, the type is locked. Create a separate item for a different type: an existing record may already be selected by themes and imports under its original meaning. The update Action rejects type changes even from forged form state or stale model instances; there is no conversion operation.

The package-owned `StructuredContentDefinitionData` projects each type's labels, description, example, named group and typed payload fields. `StructuredContentPayloadField` supplies requiredness, validation and public sanitisation. Create, update and import share the same payload validator, and public adapters use the same field membership. Non-empty incompatible fields are rejected on write, not silently carried into a new type. Historical incompatible fields are omitted from public output; editing and saving the current type removes hidden legacy fields.

Leaving the key blank derives it from the title. Keys are unique within the selected type and Site scope; a collision is suffixed rather than overwriting the existing item. Soft-deleted records continue to reserve their keys, which keeps restore safe but means a replacement may receive `-2` or a later suffix.

Summary and content provide rich-text controls without requiring HTML. They continue to store portable semantic HTML: paragraphs, headings from `h2` to `h4`, lists, links, emphasis, blockquotes, code, and line breaks. Existing portable HTML retains its semantic formatting when reopened and saved. The toolbar excludes presentation controls and media attachments. Classes, styles, IDs, data/Alpine/Livewire/event attributes, embedded media, scripts, and other presentation markup are rejected by the write Actions or safely removed by the editor before saving. Unsafe link schemes are removed. Imports use the same server validation. Put layout and visual styling in the consuming theme or widget.

Use **Preview saved content** on the edit page to inspect the saved values through the same hydrated public projection used by themes. It includes saved drafts, escapes all displayed text and does not publish anything or render your theme's layout. Unsaved edits are excluded.

## Visibility and cache behaviour

Public adapters return only items whose status is **Published** and whose Published at value is empty/past. Publishing without a date records the current time. Future dates become visible from database queries when the time arrives; there is no scheduler, queue, or publishing retry job. Draft and Archived always remain out of public output even if they retain an earlier publication date.

Items are ordered by Sort order, then title and ID. A section that shows only a few items reads only that many from the database. Public results are cached separately by Site, locale, requested types and limit for `public_cache_ttl_seconds` (five minutes by default), and never past the next publication or end date. Saving, deleting, restoring, or force-deleting an item, or changing its picture or translation, clears the package cache, Core's page caches and the affected sites' fragment and CDN entries. A scheduled `structured-content-library:invalidate-due-caches` command does the same each minute for records whose publication or end date has passed.

The public payload contains only title, slug, summary, content, and the type-specific fields. Payload values are reduced to plain text; invalid email values and unsafe URL schemes are omitted, while valid HTTP(S) and site-relative URLs are accepted. Internal model IDs, Site IDs, permissions, editor metadata, and package identifiers are not included.

## Deletion, permissions, and data handling

Delete moves an item to the trash and removes it from normal consumers. Restore is recoverable; force-delete is permanent. There is no automatic age-based retention or purge schedule. The relevant view, create, update, delete, restore, force-delete, replicate, and reorder actions remain subject to the resource policy and the actor's Site scope.

Integrators can use `ImportStructuredContentItemsAction` to create or update records by type, Site, and slug. A console import without a signed-in user runs as the system; a signed-in editor's import is checked against their permissions. Updates are enabled by default; disabling them skips matching items. The import is processed item by item rather than as one batch transaction, so records completed before a later validation or permission failure remain stored and should be reconciled from the returned counts/source data.

All titles, summaries, content, testimonial attribution, email, phone, and address payload fields are stored in plaintext. The package does not register a Privacy Centre exporter or eraser. Avoid using it for subject-request data unless the owning feature supplies export, retention, correction, and erasure handling for these records and backups.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
