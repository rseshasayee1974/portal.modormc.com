<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EwayBillDestination
{
    /** Return shipping fields only; the invoice customer remains the billed recipient. */
    public function forInvoice(Invoice $invoice, ?int $batchId = null): ?array
    {
        $sites = DB::table('mm_dispatches as d')
            ->join('mm_dispatch_statuses as ds', 'ds.dispatch_id', '=', 'd.id')
            ->leftJoin('mm_sites as s', function ($join) {
                $join->on('s.id', '=', 'd.unload_site_id')
                    ->on('s.plant_id', '=', 'd.plant_id')->whereNull('s.deleted_at');
            })
            ->where('ds.invoice_id', $invoice->id)
            ->where('d.plant_id', $invoice->plant_id)
            ->whereNull('d.deleted_at')
            ->whereNull('ds.deleted_at')
            ->when($batchId !== null, fn ($q) => $q->where('d.batch_id', $batchId))
            ->select('s.*')->distinct()->get();

        if ($sites->count() > 1) {
            throw ValidationException::withMessages(['unload_site_id' => 'This invoice has multiple unloading sites. Generate the e-way bill for a specific batch.']);
        }
        $site = $sites->first();
        if (!$site || !trim((string) $site->site_address_1)
            || !trim((string) $site->zipcode) || !trim((string) $site->state)) {
            return null;
        }

        $codes = DB::table('mm_state_codes as st')
            ->join('mm_countries as c', 'c.id', '=', 'st.country_id')
            ->whereNull('st.deleted_at')->whereNull('c.deleted_at')
            ->where('c.country_name', trim((string) ($site->country ?: 'India')))
            ->where('st.state_name', trim($site->state))
            ->distinct()->pluck('st.state_code');
        if ($codes->count() !== 1 || !ctype_digit((string) $codes->first())
            || !preg_match('/^[1-9][0-9]{5}$/', trim($site->zipcode))) {
            throw ValidationException::withMessages(['unload_site_id' => 'Correct the unloading site state and six-digit PIN code before generating the e-way bill.']);
        }

        return [
            'Addr1' => trim($site->site_address_1),
            'Addr2' => trim((string) $site->site_address_2),
            'Loc' => trim((string) ($site->city ?: ($site->district ?: $site->name))),
            'Pin' => (int) $site->zipcode,
            'Stcd' => str_pad((string) $codes->first(), 2, '0', STR_PAD_LEFT),
        ];
    }
}
