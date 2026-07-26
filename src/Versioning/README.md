# API Platform - Versioning (experimental)

Experimental, backward-compatible API versioning for the [API Platform](https://api-platform.com) framework.

Resources always represent the newest ("head") shape. Older clients receive
older-shaped responses and OpenAPI documentation, derived from a single set of
declarative version mutators described with the OpenAPI Overlay grammar.

See the design record: `docs/adr/0007-experimental-versioning-via-overlay-mutators.md`
and the glossary: [`CONTEXT.md`](./CONTEXT.md).

> [!WARNING]
>
> This component is **experimental**: its public API may change without notice.

> [!CAUTION]
>
> This is a read-only sub split of `api-platform/core`, please
> [report issues](https://github.com/api-platform/core/issues) and
> [send Pull Requests](https://github.com/api-platform/core/pulls)
> in the [core API Platform repository](https://github.com/api-platform/core).
