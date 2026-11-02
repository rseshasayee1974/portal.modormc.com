<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:49:44 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Fixtures;

use App\Models\{Batch, BatchMaterial, Dispatch, Machine, MixDesign, MixDesignItem, Patron, Personnel, Plant, Product, SalesOrder, Site};

class JayadhityaPlant2787Fixture
{
    public static function batch(): Batch
    {
        $plant = new Plant(['name' => 'SBR CONSTRUCTIONS', 'code' => '2787', 'mixer_capacity' => 1]);
        $plant->id = 2;
        $plant->setRelation('entity', null)->setRelation('addresses', collect());
        $order = new SalesOrder(['plant_id' => 2, 'order_no' => 'VIMALNATH', 'total_qty' => 20]);
        $order->id = 10;
        $mix = new MixDesign(['design_name' => 'M25 JAI ADHITA', 'design_code' => 'M25 JAI ADHITYA']);
        $mix->setRelation('items', collect());
        $order->setRelation('plant', $plant)->setRelation('mixDesign', $mix)
            ->setRelation('customer', new Patron(['legal_name' => 'VIMALNATH']))
            ->setRelation('site', new Site(['name' => 'THIRUMAZHISAI']));
        $dispatch = new Dispatch(['net_weight' => 0]);
        $dispatch->setRelation('truck', new Machine(['registration' => 'TN18AS5947']))
            ->setRelation('driver', new Personnel(['first_name' => 'CHITRASAN']));
        $batch = new Batch(['plant_id' => 2, 'sales_order_id' => 10, 'batch_no' => '2470', 'batch_size' => 6,
            'start_time' => '2026-11-01 07:54:48', 'end_time' => '2026-11-01 08:03:15']);
        $batch->id = 2;
        $batch->setRelation('plant', $plant)->setRelation('salesOrder', $order)->setRelation('workOrder', $order)
            ->setRelation('dispatches', collect([$dispatch]))->setRelation('moistureContents', collect())
            ->setRelation('operator', new Personnel(['first_name' => 'Stetter']));
        $materials = collect();
        foreach ([
            ['MSAND 1', 5100, [806,816,831,849,852,907]], ['12MM', 2940, [516,500,509,496,484,453]],
            ['20MM', 3840, [615,617,629,634,647,677]], ['CEMENT 2', 480, [82,81,79,74,82,84]],
            ['CEMENT 3', 1560, [260,263,261,258,259,261]], ['WATER', 780, [129,129,129,130,130,131]],
        ] as $productIndex => [$name, $target, $runs]) {
            $product = new Product(['title' => $name]);
            $product->setRelation('category', null);
            foreach ($runs as $index => $actual) {
                $material = new BatchMaterial(['plant_id' => 2, 'product_id' => $productIndex + 1,
                    'material_name' => $name.' - Run '.($index + 1), 'target_qty' => $target / 6, 'actual_qty' => $actual]);
                $materials->push($material->setRelation('product', $product));
            }
            $mix->items->push((new MixDesignItem(['product_id' => $productIndex + 1, 'actual_quantity' => $target / 6]))->setRelation('product', $product));
        }
        return $batch->setRelation('materials', $materials);
    }
}
