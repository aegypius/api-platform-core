# Versioning (experimental)

Glossary for the experimental API versioning context: backward-compatible,
read-only version mutation of responses and OpenAPI documentation from a single
declarative source. Terms below are specific to this context.

## Language

**Version**:
A named snapshot of the API surface, identified by the OpenAPI `info.version`
value. Ordered by the active comparator (semver by default).
_Avoid_: Revision, release

**Head**:
The newest version — the shape the resource code actually represents. Equal to
the configured `info.version`. A movable pointer: versions may exist above it
(defined-but-inactive) to keep rollbacks safe.
_Avoid_: Latest, current, tip

**Version line**:
The single total order of versions, derived by sorting the versions mutators
produce (their `for` values) plus head with the version comparator, then
published to clients. Shared contract between backend and client.
_Avoid_: Version list, history

**Version comparator**:
The pluggable total order over version identifiers used to build the version
line. Ships as semver (default) and date; implement it for a custom scheme.
_Avoid_: Sorter, ordering

**Version mutator**:
A class describing what one resource looks like *for* a given version (the older
side of a downgrade step; the newer side is the next version above it). Bound via
a repeatable attribute carrying `for`; holds class-level declarative mutations
and, when value logic is needed, methods bound by a method-level mutation
attribute. No interface.
_Avoid_: Transformer, migration, converter

**Mutation**:
A single typed change within a mutator: `Remove`, `Rename`, `ChangeType`,
`Restore`. Placement decides behaviour: on the class it is declarative-only; the
same attribute on a method also binds that method to compute the value. `Remove`
is class-only. Compiles to both an OpenAPI doc change and a response change.
Bounded vocabulary — no arbitrary callbacks.
_Avoid_: Change, patch, operation

**Downgrade chain**:
The ordered sequence of mutators applied to bring a head-shaped response or doc
down to a requested older version, newest→oldest, over the head→target span.
_Avoid_: Pipeline, stack

**Overlay**:
An OpenAPI Overlay document (`actions[]` with `target`/`update`/`remove`)
*produced* from a version's mutations to represent its doc delta. A produced
artifact, not a consumed input.
_Avoid_: Patch, diff

**Version resolver**:
The strategy that extracts the requested version from a request (default: the
`Accept-Version` header). Pluggable; also declares its cache `Vary` key.
_Avoid_: Negotiator, detector
