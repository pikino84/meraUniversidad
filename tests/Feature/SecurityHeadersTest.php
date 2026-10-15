<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CLAUDE.md §12: S23 (cabeceras de seguridad), S24 (X-Powered-By) y S27 (API pública).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider pages */
    public function test_security_headers_are_present(string $uri, int $status): void
    {
        $this->get($uri)
            ->assertStatus($status)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only')
            ->assertHeaderMissing('X-Powered-By');
    }

    public static function pages(): array
    {
        return [
            'login' => ['/login', 200],
            'catálogo' => ['/cursos', 200],
            'api' => ['/api/courses', 200],
            'no encontrado' => ['/no-existe-esta-pagina-xyz', 404],
        ];
    }

    /** @dataProvider pages */
    public function test_nothing_is_indexed_outside_production(string $uri, int $status): void
    {
        $this->app['env'] = 'staging'; // QA: university.meracorporation.com

        $this->get($uri)->assertStatus($status)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_production_pages_stay_indexable(): void
    {
        $this->app['env'] = 'production';

        $this->get('/cursos')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_hsts_only_in_production_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_public_api_is_rate_limited_and_caps_page_size(): void
    {
        $this->getJson('/api/courses')->assertHeader('X-RateLimit-Limit', 60);
        $this->getJson('/api/courses?page=1&per_page=500')->assertStatus(422);
    }
}
