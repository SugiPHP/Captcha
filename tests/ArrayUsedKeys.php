<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use SugiPHP\Captcha\UsedKeysInterface;

class ArrayUsedKeys implements UsedKeysInterface
{
    /** @var array<string, int> */
    public $keys = [];

    public function add(string $key, int $expiresAfter): bool
    {
        if (isset($this->keys[$key])) {
            return false;
        }
        $this->keys[$key] = $expiresAfter;

        return true;
    }
}
