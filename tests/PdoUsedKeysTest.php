<?php

declare(strict_types=1);

namespace SugiPHP\Captcha\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use SugiPHP\Captcha\PdoUsedKeys;

final class PdoUsedKeysTest extends TestCase
{
    /** @var PDO */
    private $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not available');
        }
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec(file_get_contents(__DIR__ . '/../schema.sql'));
    }

    public function testKeyCanBeAddedOnlyOnce(): void
    {
        $provider = new PdoUsedKeys($this->db);
        $this->assertTrue($provider->add('key', 60));
        $this->assertFalse($provider->add('key', 60));
        $this->assertTrue($provider->add('other key', 60));
    }

    public function testReleasedKeyCanBeAddedAgain(): void
    {
        $provider = new PdoUsedKeys($this->db);
        $this->assertTrue($provider->add('key', -10));
        $this->assertTrue($provider->add('key', 60));
        $this->assertFalse($provider->add('key', 60));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM captcha_used_codes')->fetchColumn());
    }
}
