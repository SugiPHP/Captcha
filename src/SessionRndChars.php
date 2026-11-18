<?php

declare(strict_types=1);

namespace SugiPHP\Captcha;

use RuntimeException;

/**
 * CAPTCHA random characters creator using GD, keeping the code in the session.
 *
 * The checksum is a random token, pointing to the code in the session, so several forms
 * (browser tabs) can be opened at the same time. Each CAPTCHA can be verified only once.
 *
 * 'data:image/jpeg;base64,' . base64_encode($captcha->getImage());
 */
final class SessionRndChars implements CaptchaInterface
{
    use RndCharsTrait;

    /**
     * Storage for configuration settings
     * @var array
     */
    private $config = [];

    /**
     * Custom storage or NULL to use $_SESSION
     * @var array|null
     */
    private $storage;

    /**
     * @var string
     */
    private $code = '';

    /**
     * @var string
     */
    private $token = '';

    /**
     * @var string
     */
    private string $lastErrorMessage = '';

    /**
     * @param array $config
     * @param array|null $storage The session data to work with. By default it is $_SESSION and the
     *                            session has to be started before create() and verify() are called.
     */
    public function __construct(array $config = array(), ?array &$storage = null)
    {
        if (!function_exists('imagettftext')) {
            throw new RuntimeException('GD library with FreeType support is required.');
        }

        // Default configuration options
        $this->config = array(
            // width of the image in pixels
            'width'                    => 120,
            // height of the image in pixels
            'height'                   => 40,
            // absolute path to the font
            'font'                     => __DIR__.'/captcha.ttf',
            // quality
            'quality'                  => 15,
            // chars that will be used on code generation
            'charset'                  => '0123456789',
            // number of chars
            'length'                   => 5,
            // when the code will expire (in seconds)
            'lifetime'                 => 60 * 60, // 1 hour
            // the session key under which the codes are kept
            'session_key'              => 'sugiphp_captcha',
            // how many not verified codes are kept in the session (the oldest ones are dropped)
            'max_pending'              => 10,
        );

        // Override default options
        foreach ($this->config as $key => $def) {
            if (isset($config[$key])) {
                $this->config[$key] = $config[$key];
            }
        }

        if (func_num_args() > 1) {
            if ($storage === null) {
                $storage = [];
            }
            $this->storage = &$storage;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create()
    {
        $pending = &$this->pending();
        $now = time();
        foreach ($pending as $token => $item) {
            if (!$this->isAlive($item, $now)) {
                unset($pending[$token]);
            }
        }
        $keep = max(1, (int) $this->config['max_pending']) - 1;
        if (count($pending) > $keep) {
            $pending = $keep ? array_slice($pending, -$keep, null, true) : [];
        }

        $this->code = $this->generateCode();
        $this->token = bin2hex(random_bytes(16));
        $pending[$this->token] = ['code' => $this->code, 'time' => $now];
    }

    /**
     * {@inheritDoc}
     */
    public function getImage()
    {
        ob_start();
        imagejpeg($this->drawImage(), null, $this->config['quality']);

        return ob_get_clean();
    }

    /**
     * Returns a token pointing to the code in the session. The code itself is not part of it.
     *
     * {@inheritDoc}
     */
    public function getChecksum(): string
    {
        if ($this->token === '') {
            throw new RuntimeException('Captcha not created');
        }

        return $this->token;
    }

    /**
     * {@inheritDoc}
     */
    public function verify($code, $checksum): bool
    {
        $code = is_string($code) ? trim($code) : '';
        if ($code === '') {
            $this->lastErrorMessage = 'Enter code from the image';
            return false;
        }
        if (!$checksum || !is_string($checksum)) {
            $this->lastErrorMessage = 'Checksum missing';
            return false;
        }
        $pending = &$this->pending();
        if (!isset($pending[$checksum]) || !is_array($pending[$checksum])) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }

        // one attempt only - right or wrong, the code cannot be used again
        $item = $pending[$checksum];
        unset($pending[$checksum]);

        if (!$this->isAlive($item, time())) {
            $this->lastErrorMessage = 'Code expired';
            return false;
        }
        if (hash_equals((string) $item['code'], $code) === false) {
            $this->lastErrorMessage = 'Wrong code';
            return false;
        }

        return true;
    }

    public function getLastErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }

    /**
     * Returns (by reference) the list of not verified codes kept in the session.
     */
    private function &pending(): array
    {
        if ($this->storage === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                throw new RuntimeException('Session is not started');
            }
            $storage = &$_SESSION;
        } else {
            $storage = &$this->storage;
        }

        $key = $this->config['session_key'];
        if (!isset($storage[$key]) || !is_array($storage[$key])) {
            $storage[$key] = [];
        }

        return $storage[$key];
    }

    /**
     * @param mixed $item
     */
    private function isAlive($item, int $now): bool
    {
        if (!is_array($item) || empty($item['code']) || empty($item['time'])) {
            return false;
        }
        $timespan = $now - $item['time'];

        return ($timespan >= 0) && ($timespan <= $this->config['lifetime']);
    }
}
