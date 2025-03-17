<?php

namespace Tests\Middlewares;

use App\Middlewares\PruneCacheMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Cache\Adapter;
use Symfony\Contracts\Cache\CacheInterface;
use Tests\TestCase;

#[CoversClass(PruneCacheMiddleware::class)]
class PruneCacheMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->set('cache_lottery', 100);
    }

    public static function pruneableCacheAdapters(): array
    {
        return [
            [Adapter\FilesystemAdapter::class],
            [Adapter\PhpFilesAdapter::class],
        ];
    }

    public static function nonPruneableCacheAdapters(): array
    {
        return [
            [Adapter\ApcuAdapter::class],
            [Adapter\ArrayAdapter::class],
            [Adapter\MemcachedAdapter::class],
            [Adapter\RedisAdapter::class],
        ];
    }

    #[Test, DataProvider('pruneableCacheAdapters')]
    public function it_prunes_the_cache_whe_using_a_pruneable_adapter_and_winning_the_lottery(string $cacheAdapter): void
    {
        /** @var CacheInterface&MockObject */
        $cache = $this->createMock($cacheAdapter);
        $cache->expects($this->once())->method('prune');

        (new PruneCacheMiddleware($this->config, $cache))(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(RequestHandlerInterface::class)
        );
    }

    #[Test, DataProvider('nonPruneableCacheAdapters')]
    public function it_does_not_prune_the_cache_when_using_a_non_prunable_adapter(string $cacheAdapter): void
    {
        /** @var CacheInterface&MockObject */
        $cache = $this->getMockBuilder($cacheAdapter)
            ->disableOriginalConstructor()
            ->getMock();

        $cache->expects($this->never())->method($this->anything());

        (new PruneCacheMiddleware($this->config, $cache))(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(RequestHandlerInterface::class)
        );
    }
}
