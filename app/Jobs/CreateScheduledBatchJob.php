<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ConcreteBatchingSchedule;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\MixDesignItem;
use App\Models\Dispatch;
use Illuminate\Support\Facades\DB;

class CreateScheduledBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $scheduleId;

    /**
     * Create a new job instance.
     */
    public function __construct($scheduleId)
    {
        $this->scheduleId = $scheduleId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $schedule = ConcreteBatchingSchedule::with(['salesOrder'])->find($this->scheduleId);
        
        if (!$schedule || $schedule->batch_id || $schedule->status === 'cancelled') {
            return;
        }

        $plantId = $schedule->plant_id;
        $salesOrder = $schedule->salesOrder;
        $mixDesignId = $schedule->mix_design_id ?? $salesOrder?->mix_design_id;
        $chosenStatus = $schedule->status;

        DB::transaction(function () use ($schedule, $plantId, $salesOrder, $mixDesignId, $chosenStatus) {
            // 1. Create Real Batch
            $nextBatchNo = (Batch::withTrashed()->where('plant_id', $plantId)->max('batch_no') ?? 0) + 1;
            
            $batchStatus = 'draft';
            if ($chosenStatus === 'loading') $batchStatus = 'in_progress';
            elseif ($chosenStatus === 'completed') $batchStatus = 'completed';
            
            $batch = Batch::create([
                'plant_id'       => $plantId,
                'sales_order_id' => $schedule->sales_order_id,
                'batch_no'       => $nextBatchNo,
                'batch_size'     => $schedule->qty_m3,
                'start_time'     => $schedule->batching_time ?? now()->format('Y-m-d H:i:s'),
                'end_time'       => $chosenStatus === 'completed' ? ($schedule->unloading_end ?? now()->format('Y-m-d H:i:s')) : null,
                'operator_id'    => $schedule->driver_id,
                'shift'          => 'A',
                'status'         => $batchStatus,
            ]);

            // 2. Sync recipe materials to BatchMaterial if mix design is provided
            if ($mixDesignId) {
                $recipeItems = MixDesignItem::where('mix_design_id', $mixDesignId)->with('product')->get();
                $ratio = (float) $schedule->qty_m3;
                foreach ($recipeItems as $item) {
                    $targetQty = round(((float)($item->actual_quantity ?? $item->cross_quantity ?? 0)) * $ratio, 3);
                    BatchMaterial::create([
                        'plant_id'           => $plantId,
                        'batch_id'           => $batch->id,
                        'product_id'         => $item->product_id,
                        'uom_id'             => $item->uom_id,
                        'material_name'      => $item->product?->title ?? 'Material',
                        'target_qty'         => $targetQty,
                        'actual_qty'         => $targetQty,
                        'deviation_quantity' => 0,
                    ]);
                }
            }

            // 3. Create Dispatch
            $dispatchDetails = $this->getNextDispatchDetails($plantId);
            $loadRate = (float) ($salesOrder?->rate ?? 0);
            $untaxAmount = round($loadRate * (float)$schedule->qty_m3, 2);

            $dispatchStatus = 'Draft';
            if ($chosenStatus === 'in_transit') $dispatchStatus = 'Shipped';
            elseif ($chosenStatus === 'completed') $dispatchStatus = 'Delivered';
            
            $dispatch = Dispatch::create([
                'plant_id'          => $plantId,
                'batch_id'          => $batch->id,
                'sales_order_id'    => $schedule->sales_order_id,
                'customer_id'       => $salesOrder?->customer_id,
                'load_site_id'      => $plantId,
                'unload_site_id'    => $schedule->site_id,
                'mixdesign_id'      => $mixDesignId,
                'truck_id'          => $schedule->vehicle_id,
                'driver_id'         => $schedule->driver_id,
                'concrete_pump'     => $schedule->pump_vehicle_id,
                'delivered_qty'     => $schedule->qty_m3,
                'dispatch_time'     => $schedule->dispatch_time ?? ($schedule->batching_time ?? now()->format('Y-m-d H:i:s')),
                'delivery_time'     => $chosenStatus === 'completed' ? ($schedule->unloading_end ?? now()->format('Y-m-d H:i:s')) : null,
                'dispatch_status'   => $dispatchStatus,
                'payment_mode'      => 'credit',
                'prefix'            => $dispatchDetails['prefix'],
                'dispatch_no'       => $dispatchDetails['nextNumber'],
                'load_rate'         => $loadRate,
                'load_tax_id'       => $salesOrder?->tax_id,
                'load_untax_amount' => $untaxAmount,
                'load_total_amount' => $untaxAmount,
            ]);

            $schedule->update([
                'batch_id' => $batch->id,
                'dispatch_id' => $dispatch->id,
            ]);

            if ($salesOrder) {
                $salesOrder->refreshProduction();
            }
        });
    }

    protected function getNextDispatchDetails(int $plantId): array
    {
        $year = date('y');
        $month = date('n');
        
        if ($month >= 4) {
            $financialYear = $year . str_pad((int)$year + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $financialYear = str_pad((int)$year - 1, 2, '0', STR_PAD_LEFT) . $year;
        }

        $prefix = "DC-{$financialYear}-";
        
        $lastDispatch = Dispatch::where('plant_id', $plantId)
            ->where('prefix', $prefix)
            ->orderBy('dispatch_no', 'desc')
            ->first();
            
        $nextNumber = $lastDispatch ? $lastDispatch->dispatch_no + 1 : 1;
        
        return [
            'prefix'     => $prefix,
            'nextNumber' => $nextNumber,
            'fullString' => $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT)
        ];
    }
}
