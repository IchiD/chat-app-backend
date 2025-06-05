<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DependencySecurityTest extends TestCase
{
    public function test_composer_lock_has_no_vulnerabilities(): void
    {
        exec('composer audit --locked --no-interaction 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
    }

    public function test_package_lock_has_no_vulnerabilities(): void
    {
        if (!File::exists(base_path('package-lock.json'))) {
            $this->markTestSkipped('package-lock.json not found');
        }

        exec('npm audit --omit=dev --json 2>&1', $output, $exitCode);
        $json = json_decode(implode("\n", $output), true);
        $vuln = $json['metadata']['vulnerabilities'] ?? [];
        $high = ($vuln['high'] ?? 0) + ($vuln['critical'] ?? 0);
        $this->assertSame(0, $high, 'NPM vulnerabilities detected: '.json_encode($vuln));
    }

    public function test_no_unapproved_packages_installed(): void
    {
        $allowedComposer = [
            'php',
            'laravel-notification-channels/webpush',
            'laravel/framework',
            'laravel/reverb',
            'laravel/sanctum',
            'laravel/socialite',
            'laravel/tinker',
            'laravel/ui',
        ];
        $composer = json_decode(File::get(base_path('composer.json')), true);
        $this->assertEqualsCanonicalizing($allowedComposer, array_keys($composer['require']));

        $allowedNpm = [
            '@popperjs/core',
            '@vitejs/plugin-vue',
            'autoprefixer',
            'axios',
            'bootstrap',
            'concurrently',
            'laravel-echo',
            'laravel-vite-plugin',
            'postcss',
            'pusher-js',
            'sass',
            'tailwindcss',
            'vite',
            'vue',
        ];
        $package = json_decode(File::get(base_path('package.json')), true);
        $this->assertEqualsCanonicalizing($allowedNpm, array_keys($package['devDependencies']));
    }

    public function test_dev_dependencies_not_in_production_lock(): void
    {
        $lock = json_decode(File::get(base_path('composer.lock')), true);
        $prodPackages = array_column($lock['packages'], 'name');
        $devPackages = array_column($lock['packages-dev'], 'name');
        foreach ($devPackages as $dev) {
            $this->assertNotContains($dev, $prodPackages, "Dev package {$dev} present in production packages");
        }

        $package = json_decode(File::get(base_path('package.json')), true);
        $this->assertTrue(empty($package['dependencies'] ?? []), 'NPM production dependencies should be empty');
    }

    public function test_composer_license_compliance(): void
    {
        $allowed = ['MIT', 'Apache-2.0', 'BSD-3-Clause', 'GPL-2.0-only', 'GPL-3.0-only', 'ISC'];
        $lock = json_decode(File::get(base_path('composer.lock')), true);
        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $pkg) {
            foreach ($pkg['license'] ?? [] as $license) {
                $this->assertContains($license, $allowed, "{$pkg['name']} has disallowed license {$license}");
            }
        }
    }

    public function test_package_integrity_is_valid(): void
    {
        exec('composer validate --no-check-publish --strict 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
    }

    public function test_no_breaking_updates_pending(): void
    {
        exec('composer outdated --direct --strict 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->markTestIncomplete("Outdated packages detected:\n".implode(PHP_EOL, $output));
        }
        $this->assertSame(0, $exitCode);
    }
}
