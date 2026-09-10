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

namespace ApiPlatform\Versioning\Tests\OpenApi;

use ApiPlatform\Versioning\OpenApi\OverlayFactory;
use PHPUnit\Framework\TestCase;

final class OverlayFactoryTest extends TestCase
{
    public function testDiffsRemovedRenamedAndChangedProperties(): void
    {
        $head = [
            'properties' => [
                'title' => ['type' => 'string'],
                'discount' => ['type' => 'integer'],
                'available' => ['type' => 'boolean'],
            ],
            'required' => ['title'],
        ];
        $mutated = [
            'properties' => [
                'name' => ['type' => 'string'],       // title renamed
                'available' => ['type' => 'integer'],  // type changed
            ],
            'required' => ['name'],
        ];

        $actions = (new OverlayFactory())->actionsForSchema('Book', $head, $mutated);

        $this->assertContains(
            ['target' => "$.components.schemas['Book'].properties['discount']", 'remove' => true],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book'].properties['title']", 'remove' => true],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book'].properties['name']", 'update' => ['type' => 'string']],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book'].properties['available']", 'update' => ['type' => 'integer']],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book'].required", 'update' => ['name']],
            $actions,
        );
    }

    public function testDiffsPropertiesNestedUnderAllOfForJsonLdSchemas(): void
    {
        $head = [
            'allOf' => [
                ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
                [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'discount' => ['type' => 'integer'],
                    ],
                    'required' => ['title'],
                ],
            ],
        ];
        $mutated = [
            'allOf' => [
                ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
                [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string']],
                    'required' => ['name'],
                ],
            ],
        ];

        $actions = (new OverlayFactory())->actionsForSchema('Book.jsonld', $head, $mutated);

        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].properties['discount']", 'remove' => true],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].properties['title']", 'remove' => true],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].properties['name']", 'update' => ['type' => 'string']],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].required", 'update' => ['name']],
            $actions,
        );
    }

    public function testNoChangeYieldsNoActions(): void
    {
        $schema = ['properties' => ['a' => ['type' => 'string']], 'required' => ['a']];
        $this->assertSame([], (new OverlayFactory())->actionsForSchema('Book', $schema, $schema));
    }

    public function testAssemblesOverlayDocument(): void
    {
        $factory = new OverlayFactory();
        $actions = [['target' => '$.x', 'remove' => true]];
        $document = $factory->createDocument('Books API', 'apple', $actions);

        $this->assertSame('1.0.0', $document['overlay']);
        $this->assertSame(['title' => 'Books API', 'version' => 'apple'], $document['info']);
        $this->assertSame($actions, $document['actions']);
    }
}
