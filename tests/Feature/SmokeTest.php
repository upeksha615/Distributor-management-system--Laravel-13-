<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Every parameter-less GET page loads for a Super Admin (catches broken views and queries). */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_simple_page_loads(): void
    {
        $admin = $this->user('super_admin');
        $this->dealer();
        $this->product();

        $failures = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods()) || str_contains($uri, '{') || in_array($uri, ['login', 'up', 'storage/{path}'])) {
                continue;
            }
            if (str_starts_with($uri, '_') || str_starts_with($uri, 'portal')) {
                continue;
            }
            $status = $this->actingAs($admin)->get('/'.ltrim($uri, '/'))->baseResponse->getStatusCode();
            if (! in_array($status, [200, 302])) {
                $failures[] = "/$uri -> $status";
            }
        }

        $this->assertSame([], $failures);
    }
}
