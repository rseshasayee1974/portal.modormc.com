<?php

namespace Tests\Feature;

use App\Http\Controllers\PurchaseOrderInwardController;
use App\Models\PurchaseOrderHistory;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InwardEditPageTest extends TestCase
{
    public function test_create_navigation_matches_the_create_route(): void
    {
        foreach (['/inventory/inwards/create', '/inventory/inwards/create/12'] as $url) {
            $route = app('router')->getRoutes()->match(Request::create($url));
            $this->assertSame('inwards.create', $route->getName());
        }
    }

    private function authorizedController(): PurchaseOrderInwardController
    {
        $controller = $this->getMockBuilder(PurchaseOrderInwardController::class)
            ->onlyMethods(['authorizeModule'])->getMock();
        $controller->expects($this->once())->method('authorizeModule')->with('edit');

        return $controller;
    }

    public function test_edit_renders_the_selected_inward_on_its_own_page(): void
    {
        session(['active_plant_id' => 6]);
        $inward = $this->getMockBuilder(PurchaseOrderHistory::class)
            ->onlyMethods(['load'])->getMock();
        $inward->setRawAttributes(['id' => 11, 'plant_id' => 6, 'inward_no' => 'INW-2627-0001']);
        $inward->expects($this->once())->method('load')->willReturnSelf();

        $request = Request::create('/inventory/inwards/11/edit', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = $this->authorizedController()->edit($inward)->toResponse($request);
        $page = $response->getData(true);

        $this->assertSame('PurchaseOrders/Inwards/Edit', $page['component']);
        $this->assertSame(11, $page['props']['inward']['id']);
        $this->assertSame('/inventory/inwards/11/edit', route('inwards.edit', 11, false));
    }

    public function test_edit_does_not_expose_another_plants_inward(): void
    {
        session(['active_plant_id' => 6]);
        $inward = new PurchaseOrderHistory(['plant_id' => 7]);

        try {
            $this->authorizedController()->edit($inward);
            $this->fail('Expected a plant-scoped 404 response.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_edit_requires_authentication(): void
    {
        try {
            (new PurchaseOrderInwardController)->edit(new PurchaseOrderHistory);
            $this->fail('Expected an authentication error.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
    }
}
