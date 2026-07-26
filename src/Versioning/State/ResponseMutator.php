<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Versioning\State;

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\Restore;
use ApiPlatform\Versioning\Metadata\BoundMutation;

/**
 * Applies an ordered downgrade chain to a single normalized item array.
 *
 * Class-level mutations are pure data operations; method-level mutations invoke
 * the bound method as (mixed $value, array $data, array $context): mixed. The
 * input array is never mutated in place — a new array is returned.
 *
 * @experimental
 */
final class ResponseMutator
{
    /** @var callable(class-string): object */
    private $locator;

    /** @var array<class-string, object> */
    private array $instances = [];

    /**
     * @param (callable(class-string): object)|null $locator resolves a mutator
     *                                                        class to an instance
     */
    public function __construct(?callable $locator = null)
    {
        $this->locator = $locator ?? static fn (string $class): object => new $class();
    }

    /**
     * @param array<string, mixed> $data
     * @param list<BoundMutation>  $chain
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function mutate(array $data, array $chain, array $context): array
    {
        foreach ($chain as $bound) {
            $data = $this->applyOne($data, $bound, $context);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function applyOne(array $data, BoundMutation $bound, array $context): array
    {
        $mutation = $bound->mutation;

        if ($mutation instanceof Remove) {
            unset($data[$mutation->property]);

            return $data;
        }

        if ($mutation instanceof Rename) {
            if (!\array_key_exists($mutation->from, $data)) {
                return $data;
            }
            // Compute before unsetting so a method-level rename still sees the
            // full item array (including the property being renamed).
            $value = $data[$mutation->from];
            $renamed = null === $bound->method
                ? $value
                : $this->invoke($bound, $value, $data, $context);
            unset($data[$mutation->from]);
            $data[$mutation->to] = $renamed;

            return $data;
        }

        if ($mutation instanceof ChangeType) {
            // Class-level ChangeType is a documentation-only delta: the value is
            // already valid for both types, so the response is left untouched.
            if (null !== $bound->method && \array_key_exists($mutation->property, $data)) {
                $data[$mutation->property] = $this->invoke($bound, $data[$mutation->property], $data, $context);
            }

            return $data;
        }

        if ($mutation instanceof Restore) {
            $data[$mutation->property] = null === $bound->method
                ? $mutation->value
                : $this->invoke($bound, null, $data, $context);

            return $data;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    private function invoke(BoundMutation $bound, mixed $value, array $data, array $context): mixed
    {
        $instance = $this->instances[$bound->mutatorClass] ??= ($this->locator)($bound->mutatorClass);

        return $instance->{$bound->method}($value, $data, $context);
    }
}
