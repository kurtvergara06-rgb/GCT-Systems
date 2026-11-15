<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class ProductionRuntimeStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_blade_vite_asset_is_a_registered_source_file(): void
    {
        $views = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(resource_path('views'))
            ),
            '/\.blade\.php$/i'
        );

        $bladeAssets = collect(iterator_to_array($views))
            ->keys()
            ->flatMap(function (string $view): array {
                preg_match_all(
                    '#resources/(?:css|js)/[A-Za-z0-9_./-]+\.(?:css|js)#',
                    (string) file_get_contents($view),
                    $matches
                );

                return $matches[0] ?? [];
            })
            ->unique()
            ->values();

        $viteConfig = (string) file_get_contents(base_path('vite.config.js'));

        $this->assertNotEmpty($bladeAssets);

        foreach ($bladeAssets as $asset) {
            $this->assertFileExists(base_path($asset), "Missing Vite source asset: {$asset}");
            $this->assertStringContainsString(
                "'{$asset}'",
                $viteConfig,
                "Blade requests an unregistered Vite entry: {$asset}"
            );
        }
    }

    public function test_runtime_preparation_creates_the_file_cache_data_directory(): void
    {
        foreach ([
            base_path('Dockerfile'),
            base_path('docker/start.sh'),
            base_path('docker/start-reverb.sh'),
            base_path('.github/workflows/ci.yml'),
            base_path('.github/workflows/production-deployment.yml'),
        ] as $file) {
            $this->assertStringContainsString(
                'storage/framework/cache/data',
                (string) file_get_contents($file),
                "Runtime preparation omits the file cache data directory: {$file}"
            );
        }
    }

    public function test_file_cache_supports_login_rate_limiting(): void
    {
        config(['cache.default' => 'file']);
        app('cache')->setDefaultDriver('file');

        $email = 'missing-user-'.bin2hex(random_bytes(6)).'@gct.test';
        $ip = '127.0.0.77';
        $loginKey = "login:{$email}|{$ip}";

        RateLimiter::clear($loginKey);

        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])
                    ->post(route('login.submit'), [
                        'email' => $email,
                        'password' => 'invalid-password',
                    ])
                    ->assertSessionHasErrors('email');
            }

            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('login.submit'), [
                    'email' => $email,
                    'password' => 'invalid-password',
                ]);

            $response->assertSessionHasErrors('email')
                ->assertSessionHas('login_lockout_seconds');
            $this->assertGreaterThan(0, RateLimiter::availableIn($loginKey));
            $this->assertLessThanOrEqual(300, RateLimiter::availableIn($loginKey));
        } finally {
            RateLimiter::clear($loginKey);
        }
    }
}
