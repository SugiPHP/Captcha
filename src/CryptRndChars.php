<?php

declare(strict_types=1);

namespace SugiPHP\Captcha;

use InvalidArgumentException;
use RuntimeException;

/**
 * CAPTCHA random characters creator using GD
 *
 * 'data:image/jpeg;base64,' . base64_encode($captcha->getImage());
 */
final class CryptRndChars implements CaptchaInterface
{
    use RndCharsTrait;

    /**
     * Encryption key (derived from the secret)
     *
     * @var string
     */
    private $encKey;

    /**
     * Message authentication key (derived from the secret)
     *
     * @var string
     */
    private $macKey;

    /**
     * @var UsedKeysProviderInterface instance
     */
    private $provider;

    /**
     * Storage for configuration settings
     * @var array
     */
    private $config = [];

    /**
     * @var string
     */
    private $code = '';

    /**
     * @var string
     */
    private string $lastErrorMessage = '';

    public function __construct(UsedKeysProviderInterface $provider, string $secret, array $config = array())
    {
        if (!function_exists('imagettftext')) {
            throw new RuntimeException('GD library with FreeType support is required.');
        }

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Secret key must be at least 32 characters long');
        }
        $this->encKey = hash_hmac('sha256', 'captcha-encryption', $secret, true);
        $this->macKey = hash_hmac('sha256', 'captcha-authentication', $secret, true);

        $this->provider = $provider;

        // Default configuration options
        $this->config = array(
            // the cipher used to encode the code
            'cipher'                   => 'aes-256-cbc',
            // width of the image in pixels
            'width'                    => 120,
            // height of the image in pixels
            'height'                   => 40,
            // absolute path to the font
            'font'                     => __DIR__.'/captcha.ttf',
            // quality
            'quality'                  => 15,
            // chars that will be used on code generation
            // 'charset'                  => 'abcdefhjkmnprstuvwxyz23456789',
            'charset'                  => '0123456789',
            // number of chars
            'length'                   => 5,
            // when the code will expire (in seconds)
            'lifetime'                 => 60 * 60, // 1 hour
        );

        // Override default options
        foreach ($this->config as $key => $def) {
            if (isset($config[$key])) {
                $this->config[$key] = $config[$key];
            }
        }

        if (!in_array($this->config['cipher'], openssl_get_cipher_methods())) {
            throw new InvalidArgumentException("Cipher {$this->config['cipher']} is not supported");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create()
    {
        $this->code = $this->generateCode();
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
     * {@inheritDoc}
     */
    public function getChecksum(): string
    {
        $plaintext = json_encode([
            'code' => $this->getPlaintextCode(),
            'time' => time(),
        ]);

        $ivlen = openssl_cipher_iv_length($this->config['cipher']);
        $iv = openssl_random_pseudo_bytes($ivlen);
        if ($iv === false) {
            throw new RuntimeException('Failed to generate initialization vector.');
        }
        $ciphertext = openssl_encrypt($plaintext, $this->config['cipher'], $this->encKey, 0, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }
        $payload = $ciphertext . '.' . base64_encode($iv);

        // the checksum is signed, so neither the ciphertext nor the IV can be altered
        return $payload . '.' . $this->sign($payload);
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
        if (!$checksum) {
            $this->lastErrorMessage = 'Checksum missing';
            return false;
        }
        $parts = explode('.', (string) $checksum, 3);
        if (count($parts) !== 3) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }
        list($ciphertext, $ivString, $signature) = $parts;
        if (!$ivString || !$ciphertext || !$signature) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }
        if (!hash_equals($this->sign($ciphertext . '.' . $ivString), $signature)) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }
        $iv = base64_decode($ivString, true);
        if (!$iv || strlen($iv) !== openssl_cipher_iv_length($this->config['cipher'])) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }

        $plaintext = openssl_decrypt($ciphertext, $this->config['cipher'], $this->encKey, 0, $iv);
        if (!$plaintext) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }
        $json = json_decode($plaintext, true);
        if (!$json) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }
        if (empty($json['code']) || empty($json['time'])) {
            $this->lastErrorMessage = 'Checksum mismatch';
            return false;
        }
        $timespan = time() - $json['time'];
        if (($timespan <= 0) || ($timespan > $this->config['lifetime'])) {
            $this->lastErrorMessage = 'Code expired';
            return false;
        }
        if (hash_equals((string) $json['code'], $code) === false) {
            $this->lastErrorMessage = 'Wrong code';
            return false;
        }

        // mark the Captcha as used for the code validity period; reject replays
        if (!$this->provider->add($ivString, $this->config['lifetime'])) {
            $this->lastErrorMessage = 'Code already used';
            return false;
        }

        return true;
    }

    /**
     * Signs the encrypted payload.
     */
    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->macKey);
    }

    /**
     * Returns secret code in plain text.
     *
     * @return string
     */
    private function getPlaintextCode(): string
    {
        if (empty($this->code)) {
            throw new RuntimeException('Captcha not created');
        }

        return $this->code;
    }

    public function getLastErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }
}
