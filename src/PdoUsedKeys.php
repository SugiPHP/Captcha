<?php

declare(strict_types=1);

namespace SugiPHP\Captcha;

use PDO;
use PDOException;
use DateTime;

class PdoUsedKeys implements UsedKeysInterface
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritDoc}
     */
    public function add(string $id, int $expiresAfter): bool
    {
        $gcRatio = 1; // how many times the garbage collector should run - in percent
        $gcRun = false;

        // check we have the key
        $row = $this->getKey($id);
        if ($row) {
            if (strtotime($row['release_time']) >= time()) {
                return false;
            }
            $this->deleteOldKeys();
            $gcRun = true;
        }

        // Actual add
        $releaseTime = new DateTime("+{$expiresAfter} seconds");
        $sql = 'INSERT INTO captcha_used_codes (id, release_time) VALUES (?, ?)';
        try {
            $sth = $this->db->prepare($sql);
            if (!$sth || !$sth->execute([$id, $releaseTime->format('Y-m-d H:i:s')])) {
                return false;
            }
        } catch (PDOException $e) {
            // the key was added by a concurrent request
            return false;
        }

        // garbage collector
        if (!$gcRun && (mt_rand(1, 100) <= $gcRatio)) {
            $this->deleteOldKeys();
        }

        return true;
    }

    private function getKey(string $id)
    {
        $sql = 'SELECT * FROM captcha_used_codes WHERE id = ?';
        $sth = $this->db->prepare($sql);
        $sth->execute([$id]);
        $res = $sth->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    /**
     * Garbage collector
     */
    private function deleteOldKeys()
    {
        $now = new DateTime();
        $sql = 'DELETE FROM captcha_used_codes WHERE release_time < ?';
        $sth = $this->db->prepare($sql);
        $sth->execute([$now->format('Y-m-d H:i:s')]);
    }
}
