<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestDatabaseIsolation
{
    /** @var resource|null */
    private static $lockHandle = null;

    public static function assertNoConfigurationCache(string $basePath): void
    {
        $cachePath = $basePath.DIRECTORY_SEPARATOR.'bootstrap'
            .DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';

        if (is_file($cachePath)) {
            throw new RuntimeException(
                'Refusing to start database tests while Laravel configuration is cached. '
                .'Run "php artisan config:clear" and verify the testing database before retrying.'
            );
        }
    }

    public static function guard(Application $app): void
    {
        $connectionName = (string) $app['config']->get('database.default');
        $connection = (array) $app['config']->get(
            "database.connections.{$connectionName}",
            []
        );
        $database = (string) ($connection['database'] ?? '');

        self::assertSafeConfiguration([
            'environment' => $app->environment(),
            'connection' => $connectionName,
            'driver' => (string) ($connection['driver'] ?? ''),
            'host' => (string) ($connection['host'] ?? ''),
            'port' => (string) ($connection['port'] ?? ''),
            'database' => $database,
            'url' => (string) ($connection['url'] ?? ''),
            'development_database' => self::dotenvValue(
                $app->basePath('.env'),
                'DB_DATABASE'
            ),
        ]);

        self::acquireExclusiveLock([
            'connection' => $connectionName,
            'driver' => (string) ($connection['driver'] ?? ''),
            'host' => (string) ($connection['host'] ?? ''),
            'port' => (string) ($connection['port'] ?? ''),
            'database' => $database,
        ]);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    public static function assertSafeConfiguration(array $context): void
    {
        $environment = strtolower(trim((string) ($context['environment'] ?? '')));
        $driver = strtolower(trim((string) ($context['driver'] ?? '')));
        $host = strtolower(trim((string) ($context['host'] ?? '')));
        $database = strtolower(trim((string) ($context['database'] ?? '')));
        $url = trim((string) ($context['url'] ?? ''));
        $developmentDatabase = strtolower(trim(
            (string) ($context['development_database'] ?? '')
        ));

        if ($environment !== 'testing') {
            throw new RuntimeException(
                "Refusing to run database tests outside APP_ENV=testing; resolved environment: {$environment}."
            );
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException(
                "Refusing to run this MySQL test suite with database driver: {$driver}."
            );
        }

        if ($url !== '') {
            throw new RuntimeException(
                'Refusing to run database tests while DB_URL is set because it can override the isolated test connection.'
            );
        }

        $allowedHosts = [
            '127.0.0.1',
            'localhost',
            '::1',
            'mysql',
            'mariadb',
            'host.docker.internal',
        ];

        if (! in_array($host, $allowedHosts, true)) {
            throw new RuntimeException(
                "Refusing to run destructive database tests on non-local host: {$host}."
            );
        }

        $protectedDatabases = [
            '',
            'gct_system',
            'information_schema',
            'mysql',
            'performance_schema',
            'sys',
        ];

        if (in_array($database, $protectedDatabases, true)) {
            throw new RuntimeException(
                "Refusing to run destructive database tests on protected database: {$database}."
            );
        }

        $looksLikeTestDatabase = preg_match(
            '/^(testing|test(?:_.+)?|.+_(?:test|testing))$/',
            $database
        ) === 1;

        if (! $looksLikeTestDatabase) {
            throw new RuntimeException(
                "Refusing to run destructive database tests because {$database} is not clearly named as a test database."
            );
        }

        if ($developmentDatabase !== '' && $database === $developmentDatabase) {
            throw new RuntimeException(
                "Refusing to run database tests because test and development both resolve to {$database}."
            );
        }
    }

    /**
     * @param  array<string, string>  $identity
     */
    private static function acquireExclusiveLock(array $identity): void
    {
        if (is_resource(self::$lockHandle)) {
            return;
        }

        $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'gct-systems-test-db-'
            .sha1(json_encode($identity, JSON_THROW_ON_ERROR)).'.lock';
        $handle = fopen($lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException(
                "Unable to create the test database lock file: {$lockPath}."
            );
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new RuntimeException(
                'Another GCT Pest process is already using this test database. '
                .'Wait for it to finish before starting another suite.'
            );
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'pid' => getmypid(),
            'database' => $identity['database'] ?? null,
            'started_at' => date(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
        fflush($handle);

        self::$lockHandle = $handle;
    }

    private static function dotenvValue(string $path, string $key): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $trimmed, 2), 2, null);

            if (trim((string) $name) !== $key) {
                continue;
            }

            return trim(trim((string) $value), "\"'");
        }

        return null;
    }
}
