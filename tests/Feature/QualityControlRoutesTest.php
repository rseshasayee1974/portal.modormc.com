<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 10:47:32 Asia/Calcutta (UTC+05:30)
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;

class QualityControlRoutesTest extends TestCase
{
    public function test_quality_menu_links_resolve_to_existing_controllers(): void
    {
        foreach ([
            '/quality/dashboard', '/quality/tests', '/quality/samples',
            '/quality/configuration/test-types', '/quality/configuration/test-parameters',
            '/quality/configuration/units', '/quality/configuration/material-mapping',
            '/quality/configuration/test-schedules', '/quality/reports',
        ] as $path) {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
            self::assertStringStartsWith('quality.', $route->getName(), $path);
            self::assertTrue(class_exists($route->getControllerClass()), $path);
            self::assertTrue(method_exists($route->getControllerClass(), $route->getActionMethod()), $path);
        }
    }

    public function test_every_quality_action_has_a_public_controller_method(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'quality.') || $route->getActionName() === 'Closure') {
                continue;
            }
            $class = $route->getControllerClass();
            $method = $route->getActionMethod();
            self::assertTrue(class_exists($class), $route->getName());
            self::assertTrue(method_exists($class, $method), $route->getName());
            self::assertTrue((new ReflectionMethod($class, $method))->isPublic(), $route->getName());
        }
    }

    public function test_frontend_quality_route_names_are_registered(): void
    {
        $names = [];
        foreach (File::allFiles(resource_path('js/Pages/Quality')) as $file) {
            preg_match_all('/[\'\"](quality\.[a-z0-9_.-]+)[\'\"]/i', $file->getContents(), $matches);
            $names = array_merge($names, $matches[1]);
        }
        self::assertNotEmpty($names);
        foreach (array_unique($names) as $name) {
            self::assertTrue(Route::has($name), "Frontend route missing: {$name}");
        }
    }

    public function test_quality_dashboard_still_requires_authentication(): void
    {
        $this->getJson('/quality/dashboard')->assertUnauthorized();
    }
}
