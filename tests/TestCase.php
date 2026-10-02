<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // A cached config (php artisan config:cache) ignores phpunit.xml's env, so tests would
        // boot with the production database and settings. Refuse instead of risking real data.
        if (file_exists(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Config is cached; run `php artisan config:clear` before testing (deploy.sh does this).');
        }

        parent::setUp();
    }
}
