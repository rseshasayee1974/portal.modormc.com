<?php

namespace Database\Seeders;

use App\Models\BatchSheetFieldDictionary;
use Illuminate\Database\Seeder;

class BatchSheetFieldDictionarySeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            // ── Header Fields ───────────────────────────────────────────────
            [
                'canonical_name' => 'batch_number',
                'aliases' => ['batch no', 'batch number', 'batch id', 'production no', 'mix no', 'load no', 'docket number', 'batch number docket number', 'delivery number', 'delivery no', 'loading no'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => 'batch_no',
                'db_table' => 'mm_batches',
            ],
            [
                'canonical_name' => 'batch_date',
                'aliases' => ['batch date', 'date', 'production date', 'batch dt', 'report date'],
                'category' => 'header',
                'data_type' => 'date',
                'db_column' => 'created_at',
                'db_table' => 'mm_batches',
            ],
            [
                'canonical_name' => 'batch_start_time',
                'aliases' => ['batch start time', 'start time', 'loading time', 'load time', 'batch start'],
                'category' => 'header',
                'data_type' => 'time',
                'db_column' => 'start_time',
                'db_table' => 'mm_batches',
            ],
            [
                'canonical_name' => 'batch_end_time',
                'aliases' => ['batch end time', 'end time', 'batch end', 'completion time'],
                'category' => 'header',
                'data_type' => 'time',
                'db_column' => 'end_time',
                'db_table' => 'mm_batches',
            ],
            [
                'canonical_name' => 'batch_size',
                'aliases' => ['batch size', 'batch qty', 'batch quantity', 'load size'],
                'category' => 'header',
                'data_type' => 'number',
                'db_column' => 'batch_size',
                'db_table' => 'mm_batches',
            ],
            [
                'canonical_name' => 'customer',
                'aliases' => ['customer', 'client', 'party', 'buyer', 'cust', 'customer name'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => 'mm_patrons',
            ],
            [
                'canonical_name' => 'site',
                'aliases' => ['site', 'site name', 'delivery site', 'project', 'location', 'destination'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => 'mm_sites',
            ],
            [
                'canonical_name' => 'truck_number',
                'aliases' => ['truck no', 'truck number', 'truck', 'vehicle', 'vehicle number', 'vehicle no', 'lorry', 'registration', 'reg no', 'reg number'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => 'mm_machines',
            ],
            [
                'canonical_name' => 'driver',
                'aliases' => ['driver', 'truck driver', 'driver name', 'chauffeur'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => 'mm_personnels',
            ],
            [
                'canonical_name' => 'recipe_name',
                'aliases' => ['recipe name', 'mix design', 'grade', 'mix', 'concrete grade', 'design name', 'mix name'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => 'mm_mix_designs',
            ],
            [
                'canonical_name' => 'recipe_code',
                'aliases' => ['recipe code', 'mix code', 'design code', 'mix id', 'recipe id'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => 'mm_mix_designs',
            ],
            [
                'canonical_name' => 'order_number',
                'aliases' => ['order no', 'order number', 'work order', 'wo no', 'wo number', 'order'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => 'order_no',
                'db_table' => 'mm_work_orders',
            ],
            [
                'canonical_name' => 'production_qty',
                'aliases' => ['production qty', 'production quantity', 'prod qty', 'total production'],
                'category' => 'header',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],
            [
                'canonical_name' => 'ordered_qty',
                'aliases' => ['ordered qty', 'ordered quantity', 'order qty', 'order quantity', 'total qty'],
                'category' => 'header',
                'data_type' => 'number',
                'db_column' => 'total_qty',
                'db_table' => 'mm_work_orders',
            ],
            [
                'canonical_name' => 'mixer_capacity',
                'aliases' => ['mixer capacity', 'mixture capacity', 'plant capacity', 'mixer cap'],
                'category' => 'metadata',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],
            [
                'canonical_name' => 'plant_type',
                'aliases' => ['plant type', 'plant model', 'batching plant'],
                'category' => 'metadata',
                'data_type' => 'string',
                'db_column' => 'plant_type',
                'db_table' => 'mm_plants',
            ],
            [
                'canonical_name' => 'plant_serial',
                'aliases' => ['plant sl no', 'plant serial number', 'plant serial', 'plant sno', 'plant sl.no'],
                'category' => 'metadata',
                'data_type' => 'string',
                'db_column' => null,
                'db_table' => null,
            ],
            [
                'canonical_name' => 'batcher_name',
                'aliases' => ['batcher name', 'batcher', 'operator', 'plant operator'],
                'category' => 'header',
                'data_type' => 'string',
                'db_column' => 'operator_id',
                'db_table' => 'mm_batches',
            ],
            [
                'canonical_name' => 'adj_manual_qty',
                'aliases' => ['adj manual qty', 'adj/manual qty', 'adjustment qty', 'manual qty', 'adj manual quantity'],
                'category' => 'header',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],
            [
                'canonical_name' => 'with_this_load',
                'aliases' => ['with this load', 'cumulative', 'running total', 'total with load'],
                'category' => 'header',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],

            // ── Material Categories ─────────────────────────────────────────
            [
                'canonical_name' => 'aggregate',
                'aliases' => ['aggregate', 'agg', 'd sand', 'm sand', 'c sand', 'p sand', 'sand', '12mm', '20mm', '12m', '20m', 'coarse aggregate', 'fine aggregate', 'csan', 'csand', 'dust', 'grit', 'agg 5', 'agg 6'],
                'category' => 'material',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => 'mm_batch_materials',
            ],
            [
                'canonical_name' => 'cement',
                'aliases' => ['cement', 'cem', 'cem1', 'cem2', 'cem3', 'cem 1', 'cem 2', 'cem 3', 'ggbs', 'fly', 'fly ash', 'opc', 'ppc', 'cem5', 'cement 1', 'cement 2'],
                'category' => 'material',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => 'mm_batch_materials',
            ],
            [
                'canonical_name' => 'water',
                'aliases' => ['water', 'wtr', 'wtr1', 'wtr 2', 'wc', 'ice', 'water/ice', 'waterice', 'water ice'],
                'category' => 'material',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => 'mm_batch_materials',
            ],
            [
                'canonical_name' => 'admixture',
                'aliases' => ['admixture', 'adm', 'adm 1', 'adm 2', 'adm 3', 'adm 4', 'admix', 'admix1', 'admix 1', 'admix 2', 'admix-1', 'aditive', 'additive', 'chemical', 'retarder', 'plasticizer'],
                'category' => 'material',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => 'mm_batch_materials',
            ],
            [
                'canonical_name' => 'silica',
                'aliases' => ['silica', 'sil', 'silica fume', 'micro silica'],
                'category' => 'material',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => 'mm_batch_materials',
            ],

            // ── Aggregate/Totals Fields ─────────────────────────────────────
            [
                'canonical_name' => 'total_set_weight',
                'aliases' => ['total set weight', 'total set weight in kg', 'total set weight in kgs', 'mass of total set weight', 'set weight', 'recipe target', 'mass of recipe targets'],
                'category' => 'aggregate',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],
            [
                'canonical_name' => 'total_actual_weight',
                'aliases' => ['total actual weight', 'total actual weight in kg', 'total actual weight in kgs', 'mass of total actual weight', 'actual weight'],
                'category' => 'aggregate',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],
            [
                'canonical_name' => 'total_difference_percent',
                'aliases' => ['difference in %', 'total mass difference', 'total mass difference in %', 'variance', 'deviation'],
                'category' => 'aggregate',
                'data_type' => 'number',
                'db_column' => null,
                'db_table' => null,
            ],
        ];

        foreach ($fields as $field) {
            BatchSheetFieldDictionary::updateOrCreate(
                ['canonical_name' => $field['canonical_name']],
                $field
            );
        }
    }
}
