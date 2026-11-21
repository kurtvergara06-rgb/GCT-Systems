<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaFoundationTest extends TestCase
{
    public function test_install_manifest_exposes_standalone_application(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_offline_worker_does_not_cache_authenticated_responses(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("event.request.mode !== 'navigate'", $worker);
        $this->assertStringContainsString("event.request.method !== 'GET'", $worker);
        $this->assertStringContainsString('fetch(event.request)', $worker);
        $this->assertStringContainsString('cache.match(OFFLINE_URL)', $worker);
        $this->assertStringNotContainsString('cache.put(event.request', $worker);
        $this->assertStringNotContainsString('caches.match(event.request', $worker);
    }

    public function test_authenticated_shell_exposes_manifest_and_shared_worker(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/Main-js/pwa-runtime.js'));

        $this->assertStringContainsString('manifest.webmanifest', $layout);
        $this->assertStringContainsString('pwa-icon.svg', $layout);
        $this->assertStringContainsString("import './Main-js/pwa-runtime.js';", $app);
        $this->assertStringContainsString("register('/sw.js'", $runtime);
        $this->assertStringNotContainsString('window.location.reload()', $runtime);
    }

    public function test_offline_fallback_requires_connection_for_secure_data(): void
    {
        $offline = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('internet connection', $offline);
        $this->assertStringContainsString('Try again', $offline);
    }
}
