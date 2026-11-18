<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SugiPHP\Captcha\CaptchaInterface;
use SugiPHP\Captcha\Session;

final class SessionTest extends TestCase
{
    /** @var array */
    private $session = [];

    protected function setUp(): void
    {
        $this->session = [];
    }

    private function captcha(array $config = []): Session
    {
        return new Session($config, $this->session);
    }

    /**
     * Reads the code from the session - the thing a human does by looking at the image.
     */
    private function solve(string $checksum): string
    {
        return $this->session['sugiphp_captcha'][$checksum]['code'];
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CaptchaInterface::class, $this->captcha());
    }

    public function testChecksumRequiresCreate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->captcha()->getChecksum();
    }

    public function testRequiresStartedSession(): void
    {
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session is not started');
        (new Session())->create();
    }

    public function testCodeUsesCharsetAndLength(): void
    {
        $captcha = $this->captcha(['charset' => 'абв', 'length' => 7]);
        $captcha->create();
        $this->assertMatchesRegularExpression('/^[абв]{7}$/u', $this->solve($captcha->getChecksum()));
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
        $captcha = $this->captcha();
        $captcha->create();
        $checksum = $captcha->getChecksum();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $checksum);
        $this->assertStringNotContainsString($this->solve($checksum), $checksum);
    }

    public function testVerifyAcceptsCorrectCodeOnlyOnce(): void
    {
        $captcha = $this->captcha();
        $captcha->create();
        $checksum = $captcha->getChecksum();
        $code = $this->solve($checksum);

        // the next request - another instance working with the same session
        $verifier = $this->captcha();
        $this->assertFalse($verifier->verify('', $checksum));
        $this->assertSame('Enter code from the image', $verifier->getLastErrorMessage());
        $this->assertFalse($verifier->verify($code, ''));
        $this->assertSame('Checksum missing', $verifier->getLastErrorMessage());
        $this->assertFalse($verifier->verify($code, 'unknown'));
        $this->assertSame('Checksum mismatch', $verifier->getLastErrorMessage());

        $this->assertTrue($verifier->verify(" {$code} ", $checksum));

        $this->assertFalse($verifier->verify($code, $checksum));
        $this->assertSame('Checksum mismatch', $verifier->getLastErrorMessage());
        $this->assertSame([], $this->session['sugiphp_captcha']);
    }

    public function testWrongCodeBurnsTheCaptcha(): void
    {
        $captcha = $this->captcha();
        $captcha->create();
        $checksum = $captcha->getChecksum();
        $code = $this->solve($checksum);

        $this->assertFalse($captcha->verify($code . 'x', $checksum));
        $this->assertSame('Wrong code', $captcha->getLastErrorMessage());
        $this->assertFalse($captcha->verify($code, $checksum));
    }

    public function testVerifyRejectsExpiredCode(): void
    {
        $captcha = $this->captcha(['lifetime' => 60]);
        $captcha->create();
        $checksum = $captcha->getChecksum();
        $code = $this->solve($checksum);
        $this->session['sugiphp_captcha'][$checksum]['time'] -= 61;

        $this->assertFalse($captcha->verify($code, $checksum));
        $this->assertSame('Code expired', $captcha->getLastErrorMessage());
    }

    public function testSeveralFormsCanBeOpenedAtOnce(): void
    {
        $first = $this->captcha();
        $first->create();
        $second = $this->captcha();
        $second->create();

        $this->assertNotSame($first->getChecksum(), $second->getChecksum());
        $verifier = $this->captcha();
        $this->assertTrue($verifier->verify($this->solve($second->getChecksum()), $second->getChecksum()));
        $this->assertTrue($verifier->verify($this->solve($first->getChecksum()), $first->getChecksum()));
    }

    public function testPendingCodesAreLimited(): void
    {
        $captcha = $this->captcha(['max_pending' => 3]);
        $captcha->create();
        $oldest = $captcha->getChecksum();
        $this->session['sugiphp_captcha']['expired'] = ['code' => '1', 'time' => time() - 7200];
        for ($i = 0; $i < 5; $i++) {
            $captcha->create();
        }

        $this->assertCount(3, $this->session['sugiphp_captcha']);
        $this->assertArrayNotHasKey($oldest, $this->session['sugiphp_captcha']);
        $this->assertArrayNotHasKey('expired', $this->session['sugiphp_captcha']);
        $this->assertArrayHasKey($captcha->getChecksum(), $this->session['sugiphp_captcha']);
    }

    public function testCustomSessionKey(): void
    {
        $captcha = $this->captcha(['session_key' => 'my_key']);
        $captcha->create();
        $this->assertSame([$captcha->getChecksum()], array_keys($this->session['my_key']));
    }
}
