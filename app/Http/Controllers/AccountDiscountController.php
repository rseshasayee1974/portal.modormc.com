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
        $discounts = AccountDiscount::with(['journal:id,voucher_number', 'account:id,title', 'partner:id,legal_name'])
            ->where('plant_id', $plantId)->orderByDesc('date')->orderByDesc('id')->get();
        $discounts->each(fn ($discount) => $discount->partner?->setAppends([]));
        return Inertia::render('Discounts/Index', [
            'discounts' => $discounts,
            'journals' => DB::table('mm_journal_entries')->where('plant_id', $plantId)->whereNull('deleted_at')
                ->orderByDesc('id')->get(['id', 'voucher_number', 'voucher_type']),
            'accounts' => DB::table('mm_ledgers')->where('plant_id', $plantId)->whereNull('deleted_at')
                ->orderBy('title')->get(['id', 'title']),
            'partners' => DB::table('mm_patrons')->where('plant_id', $plantId)->whereNull('deleted_at')
                ->orderBy('legal_name')->get(['id', 'legal_name']),
        ]);
    }

    public function show(AccountDiscount $discount)
    {
        $this->authorizeModule('show');
        $this->assertPlant($discount);
        $discount->load(['journal:id,voucher_number', 'account:id,title', 'partner:id,legal_name']);
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
        return to_route('discounts.index')->with('success', 'Discount created successfully.');
    }

    public function update(Request $request, AccountDiscount $discount)
    {
        $this->authorizeModule('edit');
        $this->assertPlant($discount);
        $discount->fill($this->validateDiscount($request, $this->plantId()));
        $discount->modified_by = auth()->id();
        $discount->save();
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

    private function validateDiscount(Request $request, int $plantId): array
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
            // Preserve the supplied schema's optional move reference without posting a new entry.
            'move_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in([0, 1])],
        ]);
        if ($data['value_type'] === 'amount') {
            $data['amount'] = $data['value'];
        }
        return $data;
    }
}
