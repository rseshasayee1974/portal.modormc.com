<?php
/*
Author: Cipher
Created: 2026-10-31 10:51:32 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class QualityRoutesTest extends TestCase
{
    public function test_quality_pages_can_resolve_their_named_routes(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js/Pages/Quality')));
        $count = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            preg_match_all("/route\(['\"](quality\.[^'\"]+)['\"]/", file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $name) {
                $this->assertTrue(Route::has($name), "$name referenced in {$file->getPathname()} is missing");
                $count++;
            }
        }
        $this->assertGreaterThan(0, $count);
        $this->assertTrue(Route::has('quality.dashboard'));
        $this->assertFalse(Route::has('quality.quality.dashboard'));
    }

    public function test_supported_quality_routes_have_controller_methods_and_matching_model_parameters(): void
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (!preg_match('#^quality/(dashboard|configuration|samples|tests|reports)(/|$)#', $route->uri())) {
                continue;
            }
            $controller = $route->getControllerClass();
            $method = $route->getActionMethod();
            $this->assertTrue(method_exists($controller, $method), "$controller::$method does not exist");
            foreach ((new \ReflectionMethod($controller, $method))->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), \Illuminate\Database\Eloquent\Model::class)) {
                    $this->assertContains($parameter->getName(), $route->parameterNames(), "Model binding mismatch for {$route->uri()}");
                }
            }
            $count++;
        }
        $this->assertGreaterThan(0, $count);
        foreach (['pending', 'completed', 'failed'] as $status) {
            $route = Route::getRoutes()->match(Request::create("/quality/tests/$status"));
            $this->assertSame("quality.tests.$status", $route->getName());
        }
    }
}
