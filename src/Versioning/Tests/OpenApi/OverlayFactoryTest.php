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
use PHPUnit\Framework\Attributes\DataProvider;
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
            ['target' => "$.components.schemas['Book.jsonld'].allOf[1].properties['discount']", 'remove' => true],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].allOf[1].properties['title']", 'remove' => true],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].allOf[1].properties['name']", 'update' => ['type' => 'string']],
            $actions,
        );
        $this->assertContains(
            ['target' => "$.components.schemas['Book.jsonld'].allOf[1].required", 'update' => ['name']],
            $actions,
        );
    }

    /**
     * @param array<string, mixed> $head
     * @param array<string, mixed> $mutated
     */
    #[DataProvider('provideHeadAndMutatedSchemas')]
    public function testAppliedActionsReproduceMutatedSchema(string $schemaName, array $head, array $mutated): void
    {
        $factory = new OverlayFactory();
        $actions = $factory->actionsForSchema($schemaName, $head, $mutated);

        $document = ['components' => ['schemas' => [$schemaName => $head]]];
        foreach ($actions as $action) {
            $document = $this->applyAction($document, $action);
        }

        $this->assertEquals($mutated, $document['components']['schemas'][$schemaName]);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function provideHeadAndMutatedSchemas(): iterable
    {
        yield 'flat schema' => [
            'Book',
            [
                'properties' => [
                    'title' => ['type' => 'string'],
                    'discount' => ['type' => 'integer'],
                    'available' => ['type' => 'boolean'],
                ],
                'required' => ['title'],
            ],
            [
                'properties' => [
                    'name' => ['type' => 'string'],
                    'available' => ['type' => 'integer'],
                ],
                'required' => ['name'],
            ],
        ];

        yield 'JSON-LD schema with properties nested under allOf' => [
            'Book.jsonld',
            [
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
            ],
            [
                'allOf' => [
                    ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
                    [
                        'type' => 'object',
                        'properties' => ['name' => ['type' => 'string']],
                        'required' => ['name'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Minimal Overlay action applier, covering only the target shapes
     * {@see OverlayFactory::actionsForSchema} emits, to verify the emitted
     * targets actually land on the head document's real nodes.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $action
     *
     * @return array<string, mixed>
     */
    private function applyAction(array $document, array $action): array
    {
        $pattern = '/^\$\.components\.schemas\[\'(?<name>[^\']+)\'\](?:\.allOf\[(?<index>\d+)\])?\.(?:properties\[\'(?<property>[^\']+)\'\]|required)$/';
        $this->assertMatchesRegularExpression($pattern, $action['target']);
        preg_match($pattern, $action['target'], $matches);

        $schema = &$document['components']['schemas'][$matches['name']];
        if (isset($matches['index']) && '' !== $matches['index']) {
            $schema = &$schema['allOf'][(int) $matches['index']];
        }

        if (isset($matches['property'])) {
            if ($action['remove'] ?? false) {
                unset($schema['properties'][$matches['property']]);
            } else {
                $schema['properties'][$matches['property']] = $action['update'];
            }
        } else {
            $schema['required'] = $action['update'];
        }

        return $document;
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
