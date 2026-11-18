<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use SugiPHP\Captcha\Captcha;

/**
 * The 1.x class - kept in 2.x for backward compatibility.
 */
final class CaptchaTest extends TestCase
{
    /** @var string[] */
    private $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        set_error_handler(function (int $errno, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testConstructorTriggersDeprecation(): void
    {
        new Captcha();
        $this->assertCount(1, $this->deprecations);
        $this->assertStringContainsString('Crypt', $this->deprecations[0]);
    }

    public function testLegacyApiStillWorks(): void
    {
        $captcha = new Captcha(['length' => 6, 'charset' => 'ab', 'width' => 150, 'height' => 50]);
        $this->assertMatchesRegularExpression('/^[ab]{6}$/', $captcha->getCode());

        $captcha->setCode('custom');
        $this->assertSame('custom', $captcha->getCode());

        $this->assertSame($captcha, $captcha->build());
        $info = getimagesizefromstring($captcha->get());
        $this->assertSame([150, 50, IMAGETYPE_JPEG], array_slice($info, 0, 3));
        $this->assertStringStartsWith('data:image/jpeg;base64,', $captcha->base64());
    }
}
