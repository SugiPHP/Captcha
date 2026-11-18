<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use SugiPHP\Captcha\CacheUsedKeys;

final class CacheUsedKeysTest extends TestCase
{
    public function testUnusedKeyIsSavedWithExpiration(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->expects($this->once())->method('set')->willReturnSelf();
        $item->expects($this->once())->method('expiresAfter')->with(60)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->with(sha1('key'))->willReturn($item);
        $pool->expects($this->once())->method('save')->with($item)->willReturn(true);

        $this->assertTrue((new CacheUsedKeys($pool))->add('key', 60));
    }

    public function testUsedKeyIsRejected(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects($this->never())->method('save');

        $this->assertFalse((new CacheUsedKeys($pool))->add('key', 60));
    }
}
