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

namespace ApiPlatform\Versioning\Tests\Fixtures;

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\VersionMutator;

#[VersionMutator(resource: Book::class, for: 'apple')]
final class BookBananaToApple
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    #[Rename(from: 'lastUpdated', to: 'updatedAt')]
    public function downgradeUpdatedAt(mixed $value, array $data, array $context): string
    {
        return (new \DateTimeImmutable((string) $value))->format('Y-m-d\TH:i:s');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    #[ChangeType(property: 'available', from: 'boolean', to: 'integer')]
    public function downgradeAvailable(mixed $value, array $data, array $context): int
    {
        return $value ? 1 : 0;
    }
}
