<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use SugiPHP\Captcha\SimpleCacheUsedKeys;

class SimpleCacheUsedKeysTest extends TestCase
{
    public function testUnusedKeyIsSavedWithExpiration(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')->with(sha1('key'))->willReturn(false);
        $cache->expects($this->once())->method('set')->with(sha1('key'), '*', 60)->willReturn(true);

        $this->assertTrue((new SimpleCacheUsedKeys($cache))->add('key', 60));
    }

    public function testUsedKeyIsRejected(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')->willReturn(true);
        $cache->expects($this->never())->method('set');

        $this->assertFalse((new SimpleCacheUsedKeys($cache))->add('key', 60));
    }
}
