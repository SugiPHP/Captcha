<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use SugiPHP\Captcha\CaptchaInterface;
use SugiPHP\Captcha\CryptRndChars;

final class CryptRndCharsTest extends TestCase
{
    private const SECRET = 'a-very-long-secret-key-of-32-chars-or-more';

    private function captcha(array $config = [], ?ArrayUsedKeysProvider $provider = null): CryptRndChars
    {
        return new CryptRndChars($provider ?: new ArrayUsedKeysProvider(), self::SECRET, $config);
    }

    /**
     * Reads the generated code - the thing a human does by looking at the image.
     */
    private function solve(CryptRndChars $captcha): string
    {
        $prop = new ReflectionProperty($captcha, 'code');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }

        return $prop->getValue($captcha);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CaptchaInterface::class, $this->captcha());
    }

    public function testShortSecretIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CryptRndChars(new ArrayUsedKeysProvider(), 'too short');
    }

    public function testUnknownCipherIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->captcha(['cipher' => 'no-such-cipher']);
    }

    public function testChecksumRequiresCreate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->captcha()->getChecksum();
    }

    public function testCodeUsesCharsetAndLength(): void
    {
        $captcha = $this->captcha(['charset' => 'абв', 'length' => 7]);
        $captcha->create();
        $this->assertMatchesRegularExpression('/^[абв]{7}$/u', $this->solve($captcha));
    }

    public function testImageIsJpegWithConfiguredSize(): void
    {
        $captcha = $this->captcha(['width' => 200, 'height' => 60]);
        $captcha->create();
        $info = getimagesizefromstring($captcha->getImage());
        $this->assertSame([200, 60, IMAGETYPE_JPEG], array_slice($info, 0, 3));
    }

    public function testChecksumDoesNotExposeTheCode(): void
    {
        $captcha = $this->captcha(['charset' => 'x', 'length' => 12]);
        $captcha->create();
        $this->assertStringNotContainsString('xxxxxxxxxxxx', $captcha->getChecksum());
    }

    public function testVerifyAcceptsCorrectCodeOnlyOnce(): void
    {
        $captcha = $this->captcha();
        $captcha->create();
        $code = $this->solve($captcha);
        $checksum = $captcha->getChecksum();
        sleep(1);

        // the verification is stateless - any instance with the same secret can do it
        $verifier = $this->captcha();
        $this->assertFalse($verifier->verify('', $checksum));
        $this->assertSame('Enter code from the image', $verifier->getLastErrorMessage());
        $this->assertFalse($verifier->verify($code, ''));
        $this->assertSame('Checksum missing', $verifier->getLastErrorMessage());
        $this->assertFalse($verifier->verify($code . 'x', $checksum));
        $this->assertSame('Wrong code', $verifier->getLastErrorMessage());

        $this->assertTrue($verifier->verify(" {$code} ", $checksum));

        $this->assertFalse($verifier->verify($code, $checksum));
        $this->assertSame('Code already used', $verifier->getLastErrorMessage());
    }

    public function testVerifyRejectsOtherSecret(): void
    {
        $captcha = $this->captcha();
        $captcha->create();
        $checksum = $captcha->getChecksum();
        sleep(1);

        $other = new CryptRndChars(new ArrayUsedKeysProvider(), str_repeat('z', 32));
        $this->assertFalse($other->verify($this->solve($captcha), $checksum));
        $this->assertSame('Checksum mismatch', $other->getLastErrorMessage());
    }

    public function testVerifyRejectsMalformedChecksums(): void
    {
        $captcha = $this->captcha();
        foreach (['garbage', 'a.b', 'a.b.c', '..', 'a.b.c.d'] as $checksum) {
            $this->assertFalse($captcha->verify('12345', $checksum), $checksum);
            $this->assertSame('Checksum mismatch', $captcha->getLastErrorMessage());
        }
    }

    public function testVerifyRejectsExpiredCode(): void
    {
        $captcha = $this->captcha(['lifetime' => 1]);
        $captcha->create();
        $checksum = $captcha->getChecksum();
        sleep(2);

        $this->assertFalse($captcha->verify($this->solve($captcha), $checksum));
        $this->assertSame('Code expired', $captcha->getLastErrorMessage());
    }

    /**
     * A solved CAPTCHA must not be reusable by altering the IV: with CBC the first plaintext
     * block (holding the code) can be rewritten at will and the IV is what marks a code as used.
     */
    public function testVerifyRejectsTamperedIv(): void
    {
        $provider = new ArrayUsedKeysProvider();
        $captcha = $this->captcha([], $provider);
        $captcha->create();
        $code = $this->solve($captcha);
        list($ciphertext, $ivString, $signature) = explode('.', $captcha->getChecksum());
        sleep(1);

        $forgedCode = strtr($code, '0123456789', '1234567890');
        $iv = base64_decode($ivString);
        for ($i = 0; $i < strlen($code); $i++) {
            // {"code":" is 9 bytes long
            $iv[9 + $i] = $iv[9 + $i] ^ $code[$i] ^ $forgedCode[$i];
        }
        $forged = $ciphertext . '.' . base64_encode($iv);

        $this->assertFalse($captcha->verify($forgedCode, $forged . '.' . $signature));
        $this->assertFalse($captcha->verify($forgedCode, $forged));
        $this->assertSame([], $provider->keys);
    }
}
