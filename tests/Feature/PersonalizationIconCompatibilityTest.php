<?php

namespace Tests\Feature;

use Tests\TestCase;

class PersonalizationIconCompatibilityTest extends TestCase
{
    public function test_font_preferences_do_not_target_every_descendant_or_replace_icon_families(): void
    {
        $css = file_get_contents(resource_path('css/Main-styles/account-preferences.css'));
        $this->assertDoesNotMatchRegularExpression('/html\[data-gct-font[^\]]*\][^{]*\*/', $css);
        foreach (['.fa-solid', '.fa-regular', '.fa-brands', '.material-icons'] as $icon) {
            $this->assertStringContainsString($icon, $css);
        }
        $this->assertStringContainsString('font-family: var(--gct-font-family)', $css);
        $this->assertStringContainsString('background: var(--surface)', $css);
    }
}
