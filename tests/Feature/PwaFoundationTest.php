<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaFoundationTest extends TestCase
{
    public function test_login_and_shell_no_longer_advertise_pwa_installation(): void
    {
        $login = file_get_contents(resource_path('views/Login/login.blade.php'));
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));

        $this->assertStringNotContainsString('manifest.webmanifest', $login);
        $this->assertStringNotContainsString('manifest.webmanifest', $layout);
        $this->assertStringNotContainsString('pwaInstallButton', $login);
        $this->assertStringContainsString('fromsDesktopDownloadButton', $login);
    }

    public function test_website_does_not_register_a_pwa_worker(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $login = file_get_contents(resource_path('js/Login/login.js'));
        $runtime = file_get_contents(resource_path('js/Main-js/desktop-runtime.js'));

        $this->assertStringContainsString("import './Main-js/desktop-runtime.js';", $app);
        $this->assertStringContainsString("import '../Main-js/desktop-runtime.js';", $login);
        $this->assertStringNotContainsString('beforeinstallprompt', $login);
        $this->assertStringContainsString('registration.unregister()', $runtime);
        $this->assertStringNotContainsString("register('/sw.js'", $runtime);
        $this->assertFileDoesNotExist(public_path('manifest.webmanifest'));
        $this->assertFileDoesNotExist(public_path('sw.js'));
        $this->assertFileDoesNotExist(public_path('offline.html'));
    }

    public function test_installer_link_is_configurable_and_disabled_until_published(): void
    {
        $this->assertArrayHasKey('installer_url', config('services.desktop'));
        $login = file_get_contents(resource_path('views/Login/login.blade.php'));
        $this->assertStringContainsString('desktopDownloadReady', $login);
        $this->assertStringContainsString('fromsDesktopInstallerLink', $login);
        $this->assertStringContainsString('Download again', $login);
    }
}
