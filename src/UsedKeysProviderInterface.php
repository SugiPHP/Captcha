<?php

declare(strict_types=1);

namespace SugiPHP\Captcha;

/**
 * Used captcha keys Provider Interface
 * The key can be only the iv or full encoded string
 */
interface UsedKeysProviderInterface
{
    /**
     * Mark a key as used for some time. If the key is already used (within the time window)
     * FALSE MUST be returned.
     *
     * @param string $key
     * @param int $expiresAfter [in seconds] - Determines when the key can be used again (frees memory)
     * @return bool TRUE if the key was added, FALSE if the key was already used
     */
    public function add(string $key, int $expiresAfter): bool;
}
