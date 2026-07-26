---
status: proposed
---

# Experimental API versioning via Overlay-described mutators

## Context

Users need to serve older clients without polluting resources with legacy
fields. Resource code should always represent the newest shape ("head"); older
API versions should be derived, not maintained by hand. We also want to keep the
OpenAPI documentation and the runtime responses consistent for any given
version, from a single source of truth.

## Decision

Introduce a new experimental `ApiPlatform\Versioning` component (opt-in by
installation; inert without mutators; `@experimental` public API).

- A **version** is the OpenAPI `info.version` value. The **version line** (total
  order) is *derived* by ordering the versions the mutators produce (their `for`
  values) together with head, using a pluggable **version comparator** (default
  semver via `version_compare`; a date comparator ships; custom allowed). There
  is no hand-maintained version list and no need to name a future version. The
  line is published to clients (`API-Supported-Versions` / `x-api-versions`).
- **Head** = configured `info.version`, a movable pointer into the line
  (lenient: versions above head are allowed but inactive, so rollbacks don't
  break boot). Requestable range is `[oldest, head]`; above-head or unknown →
  `400`.
- A **version mutator** class is bound via a repeatable
  `#[VersionMutator(resource, for)]`, where `for` is the version the mutator
  produces (the older side of its step); the newer side is the next version
  above it in the ordered line. It runs for a request at that version or older.
  It carries a bounded, declarative **mutation** vocabulary (`Remove`, `Rename`,
  `ChangeType`, `Restore`).
  Placement decides, not the kind: a mutation attribute on the **class** is
  declarative-only (e.g. a pure `Rename` key swap); the **same** attribute on a
  **method** additionally binds that method to compute the value (e.g. a
  `Rename` that also reshapes the value). `Remove` is class-only — there is no
  value to compute. There is no mutator interface; the engine
  discovers annotated methods by reflection and calls them as
  `(mixed $value, array $data, array $context): mixed` — the bound property
  value, the full item array, and the context (resource object, operation,
  fromVersion, toVersion, format), returning the property's new value. The
  same metadata drives both the documentation and the response; there are no
  arbitrary callbacks. Transforms run per item; the engine iterates
  collections; collection envelope is out of scope.
- **Responses** are downgraded by a **normalizer decorator** on the item
  normalizer — the only place the normalized array, the domain object, and
  per-item invocation coexist.
- **Documentation** is downgraded by targeted application of the compiled
  mutations to known component-schema/path locations (no JSONPath dependency),
  and a spec-compliant Overlay document plus an auto-changelog
  (`x-api-changelog`) are emitted from the same metadata.
- Version extraction is a pluggable `VersionResolverInterface` (default:
  `Accept-Version` header, configurable; emits `Vary`).

Scope is deliberately **read-only, backward-compatible only**: no request/write
downgrade, no value transforms beyond the vocabulary, no forward transforms.

## Examples

Given head `info.version: 3.0.0`, with released versions `2.0.0` and `1.0.0`
(order derived by the default semver comparator):

```php
// Pure-declarative mutator producing the 2.0.0 shape.
#[VersionMutator(resource: Book::class, for: '2.0.0')]
// 'discount' exists above 2.0.0 → dropped for 2.0.0 and older.
#[Remove('discount')]
// 2.0.0 served 'name' where head serves 'title' → value copied, key dropped.
#[Rename(from: 'title', to: 'name')]
final class BookFor2 {}
```

```php
// Method-level mutations: the same attributes placed on methods declare the
// doc delta AND process the value. One class mutates several parts. No
// interface, method names are free.
// Args of each method: bound property value, full item array, context
// (resource object, operation, fromVersion, toVersion, format). Returns the
// new value.
#[VersionMutator(resource: Book::class, for: '1.0.0')]
final class BookFor1
{
    // head: 'lastUpdated' (RFC 3339, with timezone)
    // 1.0.0: 'updatedAt' (no timezone) — rename + value reshape
    #[Rename(from: 'lastUpdated', to: 'updatedAt')]
    public function downgradeUpdatedAt(mixed $value, array $data, array $context): string
    {
        return (new \DateTimeImmutable($value))->format('Y-m-d\TH:i:s');
    }

    // head serves a boolean; 1.0.0 clients expect 0/1 — type change.
    #[ChangeType(property: 'available', from: 'boolean', to: 'integer')]
    public function downgradeAvailable(mixed $value, array $data, array $context): int
    {
        return $value ? 1 : 0;
    }
}
```

A mutation kind can appear at either level: `#[Rename]` on the class is a pure
key swap; `#[Rename]` on a method is a key swap plus value processing.
`#[Remove]` is class-only (there is no value to compute).

Repeatable, to reuse one class across resources or versions:

```php
#[VersionMutator(resource: Book::class, for: '2.0.0')]
#[VersionMutator(resource: Review::class, for: '2.0.0')]
#[Remove('internalNotes')]
final class DropInternalNotes {}
```

A request `GET /books/1` with `Accept-Version: 1.0.0` resolves the chain
`3.0.0 → 2.0.0 → 1.0.0` and applies, newest→oldest: drop `discount`, rename
`title`→`name`, then rename `lastUpdated`→`updatedAt` (stripping its timezone)
and cast `available` to `0/1`. The `1.0.0` OpenAPI document is the head document
with the equivalent emitted Overlay actions applied.

## Considered options

- **Consume user-authored overlays** to patch the generated spec — rejected:
  overlay targets address the OpenAPI document, not response data, so one
  artifact cannot drive both. A general JSONPath engine would be dead weight for
  targets we generate ourselves.
- **Arbitrary callback mutators** — rejected: unbounded power defeats the point
  of a restricted, doc-mirrorable mutation vocabulary.
- **Strict head = graph top** — rejected: couples the config pointer to the set
  of present mutator classes, making rolling deploys and rollbacks a boot gate.
- **Explicit `from`/`to` edges on each mutator** (an earlier iteration) —
  rejected: `from` (the newer side) is the version the change ships in, which is
  unknown until release; naming it at authoring time forces guessing the future.
- **A hand-maintained ordered version list in config** — rejected as
  error-prone. Instead the order is derived by a comparator over the versions
  mutators already declare (`for`), so the only value named at release is head
  (`info.version`), which is bumped anyway.

## Consequences

- Renames/type changes are expressible on both doc and response sides from one
  declaration.
- Resources stay at head shape only; older versions cost sparse mutator classes.
- Backward-compat for writes, collection-envelope changes, and forward/canary
  versions are explicit non-goals for the experimental cut and must be
  documented as limitations.
