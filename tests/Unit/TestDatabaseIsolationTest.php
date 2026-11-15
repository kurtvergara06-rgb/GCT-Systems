<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseIsolation;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_local_and_ci_test_database_names_are_allowed(): void
    {
        foreach (['testing', 'gct_system_test'] as $database) {
            TestDatabaseIsolation::assertSafeConfiguration([
                'environment' => 'testing',
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => $database,
                'url' => '',
                'development_database' => 'gct_system',
            ]);

            $this->addToAssertionCount(1);
        }
    }

    #[DataProvider('unsafeConfigurationProvider')]
    public function test_unsafe_database_configurations_fail_before_refresh_database(
        array $overrides,
        string $message
    ): void {
        $configuration = array_merge([
            'environment' => 'testing',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'testing',
            'url' => '',
            'development_database' => 'gct_system',
        ], $overrides);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        TestDatabaseIsolation::assertSafeConfiguration($configuration);
    }

    public static function unsafeConfigurationProvider(): array
    {
        return [
            'non-testing environment' => [
                ['environment' => 'local'],
                'outside APP_ENV=testing',
            ],
            'development database' => [
                ['database' => 'gct_system'],
                'protected database',
            ],
            'same development and test database' => [
                [
                    'database' => 'gct_system_test',
                    'development_database' => 'gct_system_test',
                ],
                'test and development both resolve',
            ],
            'remote host' => [
                ['host' => 'production-db.example.com'],
                'non-local host',
            ],
            'database URL override' => [
                ['url' => 'mysql://example.invalid/testing'],
                'DB_URL is set',
            ],
            'ambiguous database name' => [
                ['database' => 'capstone'],
                'not clearly named as a test database',
            ],
        ];
    }
}
