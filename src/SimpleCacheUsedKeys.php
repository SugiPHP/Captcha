<?php

declare(strict_types=1);

namespace SugiPHP\Captcha;

use Psr\SimpleCache\CacheInterface;

/**
 * Used captcha keys in a PSR-16 cache.
 * Use a cache of its own: clearing it makes all the used codes valid again.
 */
class SimpleCacheUsedKeys implements UsedKeysInterface
{
    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritDoc}
     */
    public function add(string $key, int $expiresAfter): bool
    {
        $key = sha1($key);
        if ($this->cache->has($key)) {
            return false;
        }
        // the TTL is passed on purpose: the default one of a cache can be shorter than the life of a code
        $this->cache->set($key, '*', $expiresAfter);

        return true;
    }
}
