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

namespace ApiPlatform\Versioning\Metadata;

use ApiPlatform\Versioning\Attributes\VersionMutation;
use ApiPlatform\Versioning\Attributes\VersionMutator;
use ApiPlatform\Versioning\Version\VersionStep;

/**
 * Builds a {@see MutatorRegistry} by reflecting mutator classes: it reads their
 * repeatable {@see VersionMutator} bindings, their class-level mutations, and
 * their method-level mutations (binding the method that computes the value).
 *
 * All of a class's mutations apply to every one of its bindings.
 *
 * @experimental
 */
final class MutatorMetadataFactory
{
    /**
     * @param iterable<class-string> $mutatorClasses
     */
    public function create(iterable $mutatorClasses): MutatorRegistry
    {
        /** @var array<class-string, array<string, list<BoundMutation>>> $index */
        $index = [];
        /** @var array<string, VersionStep> $steps */
        $steps = [];

        foreach ($mutatorClasses as $class) {
            $reflection = new \ReflectionClass($class);

            $bindings = array_map(
                static fn (\ReflectionAttribute $a): VersionMutator => $a->newInstance(),
                $reflection->getAttributes(VersionMutator::class),
            );

            if (!$bindings) {
                continue;
            }

            $classMutations = $this->classMutations($reflection, $class);
            $methodMutations = $this->methodMutations($reflection, $class);
            $mutations = [...$classMutations, ...$methodMutations];

            foreach ($bindings as $binding) {
                $steps[VersionStep::class.$binding->from.'>'.$binding->to] ??= new VersionStep($binding->from, $binding->to);
                $key = MutatorRegistry::key($binding->from, $binding->to);
                foreach ($mutations as $mutation) {
                    $index[$binding->resource][$key][] = $mutation;
                }
            }
        }

        return new MutatorRegistry($index, array_values($steps));
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param class-string             $class
     *
     * @return list<BoundMutation>
     */
    private function classMutations(\ReflectionClass $reflection, string $class): array
    {
        $mutations = [];
        foreach ($reflection->getAttributes() as $attribute) {
            if (is_a($attribute->getName(), VersionMutation::class, true)) {
                $mutations[] = new BoundMutation($attribute->newInstance(), $class);
            }
        }

        return $mutations;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param class-string             $class
     *
     * @return list<BoundMutation>
     */
    private function methodMutations(\ReflectionClass $reflection, string $class): array
    {
        $mutations = [];
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                if (is_a($attribute->getName(), VersionMutation::class, true)) {
                    $mutations[] = new BoundMutation($attribute->newInstance(), $class, $method->getName());
                }
            }
        }

        return $mutations;
    }
}
