# API Platform - Versioning (experimental)

Experimental, backward-compatible API versioning for the [API Platform](https://api-platform.com) framework.

Your resources always represent the **newest** ("head") shape — no legacy fields,
no branches in your entities. Older clients receive older-shaped **responses**
and older-shaped **OpenAPI documentation**, both derived from a single set of
declarative *version mutators*.

> [!WARNING]
>
> This component is **experimental**: its public API may change without notice.

## How it works

- The current API version is API Platform's `info.version` (`api_platform.version`).
  That is *head*.
- A **version mutator** describes one backward-compatible **downgrade step** from
  a newer version to the version just below it, for one resource.
- A client asks for a version with the `Accept-Version` header. API Platform
  applies the mutators from head down to that version, newest-first, to both the
  response body and the documentation. No header means head.
- Version identifiers are **opaque tokens** (`1.0.0`, `2024-11`, even `banana`).
  Their order comes only from the mutators' `from`/`to` edges — never from string
  comparison — and is published to clients.

## Enabling it

The component is **inert until you declare at least one mutator**. Installing it
adds no version headers and changes no responses on its own. Set your head
version and (optionally) the request header:

```yaml
# config/packages/api_platform.yaml
api_platform:
    version: '2.0.0'          # head
    versioning:
        header: 'Accept-Version'   # default
```

## Writing a version mutator

A mutator is a class bound to a `(resource, from, to)` step with `#[VersionMutator]`
(repeat it to reuse one class across resources or steps). Mutations are declared
with attributes.

Placement decides behaviour: a mutation attribute **on the class** is
declarative-only; the **same** attribute **on a method** additionally binds that
method to compute the value. `#[Remove]` is class-only (there is nothing to
compute).

```php
use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\VersionMutator;

// Pure-declarative step: 2.0.0 (head) -> 1.0.0
#[VersionMutator(resource: Book::class, from: '2.0.0', to: '1.0.0')]
#[Remove('discount')]              // "discount" did not exist in 1.0.0
#[Rename(from: 'title', to: 'name')] // 1.0.0 served "name"
final class BookV2ToV1
{
}
```

```php
// Step that needs value logic: 3.0.0 (head) -> 2.0.0
#[VersionMutator(resource: Book::class, from: '3.0.0', to: '2.0.0')]
final class BookV3ToV2
{
    // Rename + reshape: the method receives the bound property value, the full
    // item array, and the context (resource, operation, fromVersion, toVersion,
    // format). It returns the value for the older key.
    #[Rename(from: 'lastUpdated', to: 'updatedAt')]
    public function downgradeUpdatedAt(mixed $value, array $data, array $context): string
    {
        return (new \DateTimeImmutable((string) $value))->format('Y-m-d\TH:i:s');
    }

    // Type change: 3.0.0 serves a boolean, 2.0.0 served 0/1.
    #[ChangeType(property: 'available', from: 'boolean', to: 'integer')]
    public function downgradeAvailable(mixed $value, array $data, array $context): int
    {
        return $value ? 1 : 0;
    }
}
```

Available mutations: `Remove`, `Rename`, `ChangeType`, `Restore` (re-add a
property that existed in the older version). There are no free-form callbacks:
value logic lives in a proper, testable method, bounded by the mutation vocabulary.

## What clients see

Request an older version:

```http
GET /books/1 HTTP/1.1
Accept-Version: 1.0.0
```

Responses advertise the negotiated version and the ordered set of available
versions, and vary on the request header:

```http
Content-Version: 1.0.0
API-Supported-Versions: 3.0.0, 2.0.0, 1.0.0
Vary: Accept-Version
```

An unknown version, or one newer than head, is rejected with `400`.

The OpenAPI document for a version has its schemas downgraded, `info.version`
set, the `x-api-versions` list, and an `x-api-changelog` derived from your
mutators. A spec-compliant OpenAPI Overlay of each version delta can also be
produced.

## Limitations

- **Reads only.** Responses and documentation are downgraded. Requests (write
  payloads) are **not** upgraded — old clients must send the head shape.
- **No forward transforms.** Versions above head are not requestable (`400`).
  Promoting a version means shipping the new head and its downgrade mutator.
- **Collection envelopes are not mutated.** Only the items are; pagination and
  top-level fields are out of scope.
- **Value logic is bounded** by the mutation vocabulary — no arbitrary callbacks.

## More

- Design record: `docs/adr/0007-experimental-versioning-via-overlay-mutators.md`
- Glossary: [`CONTEXT.md`](./CONTEXT.md)

> [!CAUTION]
>
> This is a read-only sub split of `api-platform/core`, please
> [report issues](https://github.com/api-platform/core/issues) and
> [send Pull Requests](https://github.com/api-platform/core/pulls)
> in the [core API Platform repository](https://github.com/api-platform/core).
