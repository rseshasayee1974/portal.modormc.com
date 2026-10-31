<?php

/*
Author: ragul-onemodo
Created: 2026-10-31 17:25:01 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\ConcreteGradeController;
use App\Http\Controllers\MixDesignController;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\ConcreteGrade;
use App\Models\MixDesign;
use App\Services\JayadhityaBatchSheet;
use App\Services\MaterialDisplayOrder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcreteGradeMaterialOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE mm_concrete_grade_items (id INTEGER PRIMARY KEY, plant_id INTEGER, concrete_grade_id INTEGER, product_id INTEGER, quantity DECIMAL, created_by INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        (require base_path('database/migrations/2026_10_31_172500_add_display_order_to_concrete_grade_items.php'))->up();
        DB::statement('CREATE TABLE mm_concrete_grades (id INTEGER PRIMARY KEY, plant_id INTEGER, name TEXT, concrete_code TEXT, hsn_code TEXT, concrete_ratio TEXT, cement_ratio DECIMAL, sand_ratio DECIMAL, aggregate_ratio DECIMAL, status INTEGER, created_by INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_mix_designs (id INTEGER PRIMARY KEY, plant_id INTEGER, concrete_grade_id INTEGER, partner_id INTEGER, design_name TEXT, design_code TEXT, design_type TEXT, unit_id INTEGER, rate_per_qty DECIMAL, is_active INTEGER, created_by INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_mix_design_items (id INTEGER PRIMARY KEY, plant_id INTEGER, mix_design_id INTEGER, product_id INTEGER, uom_id INTEGER, rate DECIMAL, actual_quantity DECIMAL, cross_quantity DECIMAL, variation_quantity DECIMAL, created_by INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_sales_orders (id INTEGER PRIMARY KEY, plant_id INTEGER, mix_design_id INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, plant_id INTEGER, sales_order_id INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_batch_materials (id INTEGER PRIMARY KEY, plant_id INTEGER, batch_id INTEGER, product_id INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_products (id INTEGER PRIMARY KEY)');
        DB::statement('CREATE TABLE mm_product_units (id INTEGER PRIMARY KEY)');
        DB::table('mm_product_units')->insert(['id' => 1]);
        DB::table('mm_products')->insert([['id' => 10], ['id' => 20], ['id' => 30], ['id' => 40]]);
        DB::table('mm_concrete_grades')->insert(['id' => 1, 'plant_id' => 1, 'name' => 'M25']);
        DB::table('mm_mix_designs')->insert(['id' => 1, 'plant_id' => 1, 'concrete_grade_id' => 1]);
        DB::table('mm_sales_orders')->insert(['id' => 1, 'plant_id' => 1, 'mix_design_id' => 1]);
        DB::table('mm_batches')->insert(['id' => 1, 'plant_id' => 1, 'sales_order_id' => 1]);
        foreach ([10 => 30, 20 => 10, 30 => 10] as $product => $order) {
            DB::table('mm_concrete_grade_items')->insert(['plant_id' => 1, 'concrete_grade_id' => 1, 'product_id' => $product, 'quantity' => 100, 'display_order' => $order]);
        }
        foreach ([10, 20, 30, 40] as $product) {
            DB::table('mm_mix_design_items')->insert(['plant_id' => 1, 'mix_design_id' => 1, 'product_id' => $product]);
            DB::table('mm_batch_materials')->insert(['plant_id' => 1, 'batch_id' => 1, 'product_id' => $product]);
        }
        (require base_path('database/migrations/2026_10_31_173000_add_display_order_to_mix_design_items.php'))->up();
    }

    public function test_grade_mix_and_existing_batch_follow_order_with_stable_ties_and_unmapped_materials_last(): void
    {
        $this->assertSame([20, 30, 10], ConcreteGrade::find(1)->items->pluck('product_id')->all());
        $mix = MixDesign::withoutGlobalScopes()->find(1);
        $batch = Batch::withoutGlobalScopes()->find(1);
        $this->assertSame([20, 30, 10, 40], $mix->items->pluck('product_id')->all());
        $this->assertSame([20, 30, 10, 40], $batch->materials->pluck('product_id')->all());
        DB::table('mm_concrete_grade_items')->where('product_id', 10)->update(['display_order' => 1]);
        $this->assertSame([20, 30, 10, 40], $mix->items()->pluck('product_id')->all());
        DB::table('mm_mix_design_items')->where('product_id', 10)->update(['display_order' => 1]);
        $this->assertSame([10, 20, 30, 40], $mix->items()->pluck('product_id')->all());
        $this->assertSame([10, 20, 30, 40], $batch->materials()->pluck('product_id')->all());
    }

    public function test_raw_editor_queries_follow_the_same_order(): void
    {
        $this->assertSame([20, 30, 10, 40], DB::table('mm_batch_materials as bm')->orderByRaw(MaterialDisplayOrder::batchMaterial('bm'))->orderBy('bm.id')->pluck('product_id')->all());
        $this->assertSame([20, 30, 10, 40], DB::table('mm_mix_design_items as mdi')->orderByRaw(MaterialDisplayOrder::mixDesignItem('mdi'))->orderBy('mdi.id')->pluck('product_id')->all());
    }

    public function test_soft_deleted_mix_rows_and_other_plant_rows_cannot_supply_the_order(): void
    {
        DB::table('mm_mix_design_items')->where('product_id', 20)->update(['deleted_at' => '2026-10-31']);
        DB::table('mm_mix_design_items')->where('product_id', 30)->update(['plant_id' => 2]);
        $this->assertSame([10, 40, 20, 30], Batch::withoutGlobalScopes()->find(1)->materials->pluck('product_id')->all());
    }

    private function controller(): ConcreteGradeController
    {
        return new class extends ConcreteGradeController {
            protected function authorizeModule(string $action, ?string $module = null): void {}
        };
    }

    private function payload(): array
    {
        return ['name' => 'M30', 'concrete_code' => 'M30', 'concrete_ratio' => null, 'cement_ratio' => null, 'sand_ratio' => null, 'aggregate_ratio' => null,
            'items' => [['product_id' => 10, 'quantity' => 300, 'display_order' => 9], ['product_id' => 20, 'quantity' => 600, 'display_order' => 2]]];
    }

    public function test_grade_save_and_update_persist_order_without_changing_recipe_quantities(): void
    {
        session(['active_plant_id' => 1]);
        EloquentModel::withoutEvents(fn () => $this->controller()->store(Request::create('/', 'POST', $this->payload())));
        $grade = ConcreteGrade::where('name', 'M30')->firstOrFail();
        $this->assertSame([20, 10], $grade->items->pluck('product_id')->all());
        $payload = $this->payload();
        $payload['items'][0]['display_order'] = 0;
        unset($payload['items'][1]['display_order']);
        EloquentModel::withoutEvents(fn () => $this->controller()->update(Request::create('/', 'PUT', $payload), $grade));
        $this->assertSame([10, 20], $grade->items()->pluck('product_id')->all());
        $this->assertEquals([0, 2], $grade->items()->pluck('display_order')->all());
        $this->assertEquals(900, $grade->items()->sum('quantity'));
    }

    public function test_negative_material_order_is_rejected(): void
    {
        session(['active_plant_id' => 1]);
        $payload = $this->payload();
        $payload['items'][0]['display_order'] = -1;
        try {
            $this->controller()->store(Request::create('/', 'POST', $payload));
            $this->fail('Invalid display order was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.display_order', $exception->errors());
        }
    }

    private function mixController(): MixDesignController
    {
        return new class extends MixDesignController {
            protected function authorizeModule(string $action, ?string $module = null): void {}
        };
    }

    public function test_mix_items_save_grade_defaults_and_custom_order_and_can_update_independently(): void
    {
        session(['active_plant_id' => 1]);
        $payload = ['partner_id' => null, 'design_name' => 'Ordered Mix', 'design_code' => 'ORDER', 'design_type' => 'M25', 'unit_id' => 1, 'rate_per_qty' => 100,
            'items' => [
                ['product_id' => 10, 'uom_id' => 1, 'rate' => 2, 'actual_quantity' => 300, 'cross_quantity' => 300, 'variation_quantity' => 0, 'display_order' => 2],
                ['product_id' => 20, 'uom_id' => 1, 'rate' => 2, 'actual_quantity' => 600, 'cross_quantity' => 600, 'variation_quantity' => 0],
            ]];
        EloquentModel::withoutEvents(fn () => $this->mixController()->store(Request::create('/', 'POST', $payload)));
        $mix = MixDesign::where('design_name', 'Ordered Mix')->firstOrFail();
        $this->assertSame([2, 10], $mix->items()->pluck('display_order')->all());
        foreach ($payload['items'] as &$item) {
            $item['id'] = $mix->items()->where('product_id', $item['product_id'])->value('id');
        }
        unset($item);
        $payload['items'][0]['display_order'] = 30;
        $payload['items'][1]['display_order'] = 0;
        EloquentModel::withoutEvents(fn () => $this->mixController()->update(Request::create('/', 'PUT', $payload), $mix));
        $this->assertSame([20, 10], $mix->items()->pluck('product_id')->all());
        $this->assertSame([0, 30], $mix->items()->pluck('display_order')->all());
        $this->assertEquals(900, $mix->items()->sum('actual_quantity'));
    }

    public function test_mix_item_order_rejects_negative_values(): void
    {
        session(['active_plant_id' => 1]);
        $payload = ['design_name' => 'Invalid', 'items' => [['product_id' => 10, 'uom_id' => 1, 'actual_quantity' => 1, 'display_order' => -1]]];
        try {
            $this->mixController()->store(Request::create('/', 'POST', $payload));
            $this->fail('Invalid mix display order was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.display_order', $exception->errors());
        }
    }

    public function test_batch_sheet_keeps_cross_category_order_and_totals(): void
    {
        $materials = collect();
        foreach (['CEMENT 1', '20MM', 'WATER', '12MM'] as $index => $name) {
            $material = new BatchMaterial(['material_name' => $name, 'target_qty' => 100, 'actual_qty' => 101]);
            $material->setRelation('product', null);
            $materials->push($material);
        }
        $batch = (new Batch(['batch_size' => 1]))->setRelation('materials', $materials)->setRelation('plant', null)->setRelation('salesOrder', null);
        $report = JayadhityaBatchSheet::data($batch);
        $this->assertSame(['CEMENT 1', '20MM', 'WATER', '12MM'], $report['columns']);
        $this->assertSame(['Cement', 'Aggregate', 'Water / Ice', 'Aggregate'], array_column($report['group_spans'], 'name'));
        $this->assertEquals(400, $report['total_target']);
        $this->assertEquals(404, $report['total_actual']);
    }
}
