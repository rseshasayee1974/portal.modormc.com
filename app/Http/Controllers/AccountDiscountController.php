<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AccountDiscount;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountDiscountController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'discounts';

    private function plantId(): int
    {
        $plantId = app(PlantContextService::class)->plantId();
        abort_unless($plantId, 403, 'Select an active plant to manage discounts.');
        return $plantId;
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = $this->plantId();
        $discounts = AccountDiscount::with([
            'journal:id,voucher_number,voucher_type,ref_module,total_debit,total_credit',
            'account:id,title',
            'partner:id,legal_name',
            'invoice:id,invoice_number,prefix',
            'bill:id,invoice_number,prefix',
        ])
            ->where('plant_id', $plantId)->orderByDesc('date')->orderByDesc('id')->get();
        $discounts->each(fn ($discount) => $discount->partner?->setAppends([]));
        $appliedDiscounts = DB::table('mm_account_discount')
            ->where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->get(['id', 'journal_id', 'invoice_id', 'billing_id', 'payment_id']);

        $appliedJournalMap = [];
        $appliedInvoiceMap = [];
        $appliedBillingMap = [];
        $appliedPaymentMap = [];

        foreach ($appliedDiscounts as $ad) {
            if ($ad->journal_id) $appliedJournalMap[(int)$ad->journal_id] = (int)$ad->id;
            if ($ad->invoice_id) $appliedInvoiceMap[(int)$ad->invoice_id] = (int)$ad->id;
            if ($ad->billing_id) $appliedBillingMap[(int)$ad->billing_id] = (int)$ad->id;
            if ($ad->payment_id) $appliedPaymentMap[(int)$ad->payment_id] = (int)$ad->id;
        }

        $journals = DB::table('mm_journal_entries as j')
            ->leftJoin('mm_invoices as inv', function ($join) {
                $join->on('j.ref_id', '=', 'inv.id')
                    ->whereNull('inv.deleted_at');
            })
            ->leftJoin('mm_journal_entry_lines as l', function ($join) {
                $join->on('j.id', '=', 'l.journal_entry_id')
                    ->whereNotNull('l.partner_id');
            })
            ->leftJoin('mm_patrons as p_inv', 'inv.partner_id', '=', 'p_inv.id')
            ->leftJoin('mm_patrons as p_line', 'l.partner_id', '=', 'p_line.id')
            ->where('j.plant_id', $plantId)
            ->whereNull('j.deleted_at')
            ->select([
                'j.id',
                'j.voucher_number',
                'j.voucher_type',
                'j.ref_module',
                'j.ref_id',
                'inv.id as invoice_doc_id',
                'j.total_debit',
                'j.total_credit',
                DB::raw('COALESCE(inv.partner_id, MAX(l.partner_id)) as partner_id'),
                DB::raw('COALESCE(MAX(p_inv.legal_name), MAX(p_line.legal_name)) as partner_name'),
                DB::raw('MAX(inv.invoice_number) as invoice_number'),
            ])
            ->groupBy(
                'j.id',
                'j.voucher_number',
                'j.voucher_type',
                'j.ref_module',
                'j.ref_id',
                'inv.id',
                'j.total_debit',
                'j.total_credit',
                'inv.partner_id'
            )
            ->orderByDesc('j.id')
            ->get()
            ->map(function ($j) use ($appliedJournalMap, $appliedInvoiceMap, $appliedBillingMap, $appliedPaymentMap) {
                $refMod = strtolower($j->ref_module ?? '');
                $appliedId = $appliedJournalMap[(int)$j->id] ?? null;

                if (!$appliedId && $j->ref_id) {
                    $refId = (int)$j->ref_id;
                    if ($refMod === 'invoice' || $refMod === 'sales') {
                        $appliedId = $appliedInvoiceMap[$refId] ?? null;
                    } elseif ($refMod === 'bill' || $refMod === 'purchase') {
                        $appliedId = $appliedBillingMap[$refId] ?? null;
                    } elseif ($refMod === 'payment' || $refMod === 'receipt') {
                        $appliedId = $appliedPaymentMap[$refId] ?? null;
                    }
                }

                $j->applied_discount_id = $appliedId ? (int)$appliedId : null;
                return $j;
            });
// return response()->json([$journals,$discounts]);
        return Inertia::render('Discounts/Index', [
            'discounts' => $discounts,
            'journals' => $journals,
            'accounts' => DB::table('mm_ledgers')->where('plant_id', $plantId)->whereNull('deleted_at')
                ->orderBy('title')->get(['id', 'title']),
            'partners' => DB::table('mm_patrons')->where('plant_id', $plantId)->whereNull('deleted_at')
                ->orderBy('legal_name')->get(['id', 'legal_name', 'patron_type']),
        ]);
    }

    public function show(AccountDiscount $discount)
    {
        $this->authorizeModule('show');
        $this->assertPlant($discount);
        $discount->load([
            'journal:id,voucher_number,voucher_type,ref_module,total_debit,total_credit',
            'account:id,title',
            'partner:id,legal_name',
            'invoice:id,invoice_number,prefix',
            'bill:id,invoice_number,prefix',
        ]);
        $discount->partner?->setAppends([]);
        return response()->json($discount);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = $this->plantId();
        $discount = new AccountDiscount($this->validateDiscount($request, $plantId));
        $discount->plant_id = $plantId;
        $discount->created_by = auth()->id();
        $discount->modified_by = auth()->id();
        $discount->save();

        $discount->postToAccounting();

        return to_route('discounts.index')->with('success', 'Discount created successfully.');
    }

    public function update(Request $request, AccountDiscount $discount)
    {
        $this->authorizeModule('edit');
        $this->assertPlant($discount);
        $discount->fill($this->validateDiscount($request, $this->plantId(), $discount->id));
        $discount->modified_by = auth()->id();
        $discount->save();

        $discount->postToAccounting();

        return to_route('discounts.index')->with('success', 'Discount updated successfully.');
    }

    public function destroy(AccountDiscount $discount)
    {
        $this->authorizeModule('delete');
        $this->assertPlant($discount);
        $discount->delete();
        return to_route('discounts.index')->with('success', 'Discount deleted successfully.');
    }

    private function assertPlant(AccountDiscount $discount): void
    {
        abort_unless((int) $discount->plant_id === $this->plantId(), 404);
    }

    private function validateDiscount(Request $request, int $plantId, ?int $ignoreDiscountId = null): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('plant_id', $plantId)->whereNull('deleted_at');
        $money = ['numeric', 'gt:0', 'regex:/^\d{1,15}(\.\d{1,2})?$/'];
        $data = $request->validate([
            'primary_type' => ['required', Rule::in(['Sales', 'Purchase'])],
            'value_type' => ['required', Rule::in(['percent', 'amount'])],
            'value' => array_merge(['required'], $money, $request->input('value_type') === 'percent' ? ['max:100'] : []),
            'amount' => array_merge([Rule::requiredIf($request->input('value_type') === 'percent'), 'nullable'], $money),
            'journal_id' => ['required', 'integer', $exists('mm_journal_entries')],
            'account_id' => ['nullable', 'integer', $exists('mm_ledgers')],
            'partner_id' => ['required', 'integer', $exists('mm_patrons')],
            'invoice_id' => ['nullable', 'integer'],
            'billing_id' => ['nullable', 'integer'],
            'payment_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            // Preserve the supplied schema's optional move reference without posting a new entry.
            'move_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in([0, 1])],
        ]);

        $selectedJournalId = (int) $request->input('journal_id');
        if ($selectedJournalId) {
            $alreadyApplied = DB::table('mm_account_discount')
                ->where('plant_id', $plantId)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->where('journal_id', $selectedJournalId)
                ->when($ignoreDiscountId, fn($q) => $q->where('id', '!=', $ignoreDiscountId))
                ->exists();

            if ($alreadyApplied) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'journal_id' => 'This invoice/bill/voucher has already been applied to another discount.',
                ]);
            }
        }

        if ($data['value_type'] === 'amount') {
            $data['amount'] = $data['value'];
        }
        if (!empty($data['journal_id']) && empty($data['move_id'])) {
            $data['move_id'] = (int) $data['journal_id'];
        }
        return $data;
    }
}