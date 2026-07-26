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

namespace ApiPlatform\Versioning\Tests\Symfony\DependencyInjection;

use ApiPlatform\State\SerializerContextBuilderInterface;
use ApiPlatform\Versioning\Metadata\MutatorRegistry;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\State\ResponseMutator;
use ApiPlatform\Versioning\Symfony\DependencyInjection\VersioningPass;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionComparatorInterface;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class VersioningPassTest extends TestCase
{
    private const CONFIG_DIR = __DIR__.'/../../../../Symfony/Bundle/Resources/config';

    private function container(bool $withMutators = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('api_platform.version', 'cherry');
        $container->setParameter('api_platform.versioning.header', 'Accept-Version');

        // Stubs for the services the versioning config decorates.
        $container->register('api_platform.serializer.context_builder', StubContextBuilder::class);
        $container->register('api_platform.serializer.normalizer.item', StubItemNormalizer::class);
        $container->register('api_platform.openapi.normalizer', StubItemNormalizer::class);

        if ($withMutators) {
            foreach ([BookCherryToBanana::class, BookBananaToApple::class, DropInternalNotes::class] as $class) {
                $container->register($class, $class)->addTag(VersioningPass::MUTATOR_TAG);
            }
        }

        (new PhpFileLoader($container, new FileLocator(self::CONFIG_DIR)))->load('versioning.php');

        // Fixtures use opaque fruit tokens; order them with a custom comparator.
        $container->register(FruitComparator::class, FruitComparator::class);
        $container->setAlias(VersionComparatorInterface::class, FruitComparator::class);

        return $container;
    }

    public function testStaysInertWithoutMutators(): void
    {
        $container = $this->container(withMutators: false);
        (new VersioningPass())->process($container);

        // The request/response hooks are removed so behaviour is unchanged.
        $this->assertFalse($container->hasDefinition('api_platform.versioning.serializer.context_builder'));
        $this->assertFalse($container->hasDefinition('api_platform.versioning.openapi.normalizer'));
        $this->assertFalse($container->hasDefinition('api_platform.versioning.event_listener.add_headers'));
        $this->assertFalse($container->hasDefinition('api_platform.serializer.normalizer.item.versioning'));
    }

    public function testPassWiresRegistryLocatorAndNormalizerDecoration(): void
    {
        $container = $this->container();
        (new VersioningPass())->process($container);

        $registryArg = $container->getDefinition('api_platform.versioning.registry')->getArgument(0);
        $this->assertContains(BookBananaToApple::class, $registryArg);

        $locatorArg = $container->getDefinition('api_platform.versioning.response_mutator')->getArgument(0);
        $this->assertInstanceOf(Reference::class, $locatorArg);

        $this->assertTrue($container->hasDefinition('api_platform.serializer.normalizer.item.versioning'));
        $decorator = $container->getDefinition('api_platform.serializer.normalizer.item.versioning');
        $this->assertSame(VersionMutationNormalizer::class, $decorator->getClass());
        $this->assertSame('api_platform.serializer.normalizer.item', $decorator->getDecoratedService()[0]);
    }

    public function testCompiledServicesBehaveEndToEnd(): void
    {
        $container = $this->container();
        $container->addCompilerPass(new VersioningPass());
        foreach ([
            'api_platform.versioning.registry',
            'api_platform.versioning.graph',
            'api_platform.versioning.chain_resolver',
            'api_platform.versioning.response_mutator',
        ] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $container->compile();

        /** @var MutatorRegistry $registry */
        $registry = $container->get('api_platform.versioning.registry');
        $this->assertContains(Book::class, $registry->getResources());

        /** @var VersionGraph $graph */
        $graph = $container->get('api_platform.versioning.graph');
        $this->assertSame('cherry', $graph->getHead());
        $this->assertSame(['cherry', 'banana', 'apple'], $graph->getVersions());

        /** @var DowngradeChainResolver $resolver */
        $resolver = $container->get('api_platform.versioning.chain_resolver');
        /** @var ResponseMutator $mutator */
        $mutator = $container->get('api_platform.versioning.response_mutator');

        $result = $mutator->mutate(
            ['title' => 'Foo', 'discount' => 5, 'available' => true],
            $resolver->resolve(Book::class, 'apple'),
            [],
        );

        // discount removed, title->name, available bool->int via the located mutator instance.
        $this->assertEqualsCanonicalizing(['name' => 'Foo', 'available' => 1], $result);
    }
}

final class StubContextBuilder implements SerializerContextBuilderInterface
{
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        return [];
    }
}

final class StubItemNormalizer implements NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }
}
