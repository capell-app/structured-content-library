# Changelog

All notable changes to `capell-app/structured-content-library` will be documented in this file.

## Unreleased

- Security: public adapter payloads are now filtered to plain text plus safe HTTP(S), relative URL, and valid email values before themes receive them.
- Published structured content now receives a `published_at` timestamp automatically when saved as published without an explicit date.
- Imports now deduplicate records by the generated title slug when no slug is supplied, and fresh installs create a scoped unique index for type/site/slug.

## 2026-06-03

- Security: sanitise structured content summaries through the portable HTML guard during create and update actions, rejecting scripts, inline event handlers, and designed markup before storage.
- Implemented real `StructuredContentLibraryHealthCheck` diagnostics for storage, model registration, protected table registration, and admin resource registration.
- Rewrote marketplace summary and package descriptions (`capell.json` and `composer.json`) to describe the typed, theme-safe content library.
