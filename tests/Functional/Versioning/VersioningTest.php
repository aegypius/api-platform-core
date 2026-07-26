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

namespace ApiPlatform\Tests\Functional\Versioning;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Tests\Fixtures\TestBundle\ApiResource\Versioning\VersionedBook;
use ApiPlatform\Tests\Fixtures\TestBundle\State\VersionedBookV2ToV1;
use ApiPlatform\Tests\SetupClassResourcesTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class VersioningAppKernel extends \AppKernel
{
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        parent::configureContainer($c, $loader);

        // Head version for this app.
        $c->prependExtensionConfig('api_platform', ['version' => '2.0.0']);

        // Register the mutator as an autoconfigured service so #[VersionMutator]
        // tags it and the versioning pass wires it.
        $c->register(VersionedBookV2ToV1::class, VersionedBookV2ToV1::class)
            ->setAutoconfigured(true)
            ->setPublic(false);
    }
}

final class VersioningTest extends ApiTestCase
{
    use SetupClassResourcesTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected static function getKernelClass(): string
    {
        return VersioningAppKernel::class;
    }

    /**
     * @return class-string[]
     */
    public static function getResources(): array
    {
        return [VersionedBook::class];
    }

    public function testNoVersionHeaderServesHead(): void
    {
        $response = self::createClient()->request('GET', '/versioned_book', ['headers' => ['accept' => 'application/json']]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('The Pragmatic Programmer', $data['title']);
        $this->assertSame(10, $data['discount']);
        $this->assertTrue($data['available']);
        $this->assertArrayHasKey('lastUpdated', $data);
        $this->assertArrayNotHasKey('name', $data);
    }

    public function testAcceptVersionDowngradesTheResponse(): void
    {
        $response = self::createClient()->request('GET', '/versioned_book', ['headers' => [
            'accept' => 'application/json',
            'Accept-Version' => '1.0.0',
        ]]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // discount removed, title -> name, lastUpdated -> updatedAt (tz stripped),
        // available boolean -> 0/1, isbn untouched.
        $this->assertArrayNotHasKey('discount', $data);
        $this->assertArrayNotHasKey('title', $data);
        $this->assertArrayNotHasKey('lastUpdated', $data);
        $this->assertSame('The Pragmatic Programmer', $data['name']);
        $this->assertSame('2026-01-02T03:04:05', $data['updatedAt']);
        $this->assertSame(1, $data['available']);
        $this->assertSame('978-0135957059', $data['isbn']);
    }

    public function testDiscoverabilityHeaders(): void
    {
        $response = self::createClient()->request('GET', '/versioned_book', ['headers' => [
            'accept' => 'application/json',
            'Accept-Version' => '1.0.0',
        ]]);

        $headers = $response->getHeaders();
        $this->assertSame('1.0.0', $headers['content-version'][0]);
        $this->assertSame('2.0.0, 1.0.0', $headers['api-supported-versions'][0]);
        $this->assertStringContainsString('Accept-Version', implode(',', $headers['vary']));
    }

    public function testUnknownVersionIsRejected(): void
    {
        self::createClient()->request('GET', '/versioned_book', ['headers' => [
            'accept' => 'application/json',
            'Accept-Version' => 'banana',
        ]]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDocsEndpointDowngradesTheSchema(): void
    {
        $response = self::createClient()->request('GET', '/docs', ['headers' => [
            'Accept' => 'application/vnd.openapi+json',
            'Accept-Version' => '1.0.0',
        ]]);

        $this->assertResponseIsSuccessful();
        $doc = $response->toArray();
        $schema = $doc['components']['schemas']['VersionedBook']['properties'];

        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayNotHasKey('title', $schema);
        $this->assertArrayNotHasKey('discount', $schema);
        $this->assertArrayHasKey('updatedAt', $schema);
        $this->assertSame('integer', $schema['available']['type']);
        $this->assertSame('1.0.0', $doc['info']['version']);
    }

    public function testDocsEndpointHeadPublishesTheVersionLine(): void
    {
        $doc = self::createClient()->request('GET', '/docs', ['headers' => [
            'Accept' => 'application/vnd.openapi+json',
        ]])->toArray();

        // No Accept-Version → head (2.0.0); schema untouched, versions published.
        $this->assertArrayHasKey('title', $doc['components']['schemas']['VersionedBook']['properties']);
        $this->assertSame('2.0.0', $doc['info']['version']);
        $this->assertSame(['2.0.0', '1.0.0'], $doc['info']['x-api-versions']);
    }
}
