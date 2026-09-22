<?php
namespace App\Services;

use App\Models\{OpeningBalanceBatch, OpeningBalanceAuditLog, JournalEntry, Plant, Patron};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IndividualOpeningBalanceService
{
    public function save(array $input, int $plantId, int $userId): OpeningBalanceBatch
    {
        return DB::transaction(function () use ($input, $plantId, $userId) {
            $plant = Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            abort_if($this->legacy($plantId)->exists(), 409, 'Convert the previous bulk setup before saving individual balances.');
            Validator::make($input, [
                'record_id'=>'nullable|integer', 'version'=>'required|integer|min:0',
                'balance_type'=>'required|in:patron,ledger',
                'account_id'=>'exclude_if:balance_type,patron|required|integer', 'patron_id'=>'nullable|required_if:balance_type,patron|prohibited_if:balance_type,ledger|integer',
                'side'=>'required|in:Dr,Cr',
                'clearing_account_id'=>'required|integer', 'reason'=>'required|string|min:5|max:1000',
            ])->validate();
            if ($input['balance_type'] === 'patron') {
                $patron = Patron::withoutGlobalScope('plant_id')->where('plant_id', $plantId)
                    ->lockForUpdate()->find($input['patron_id']);
                if (!$patron) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['patron_id'=>'Select an active customer or vendor belonging to this plant.']);
                }
                $field = $input['side'] === 'Dr' ? 'debit_ledger_id' : 'credit_ledger_id';
                if (!$patron->$field) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['patron_id'=>"Configure the patron's {$field} before saving a {$input['side']} opening balance."]);
                }
                // Resolve from the master on every save; never accept the client account for a patron.
                $input['account_id'] = (int) $patron->$field;
            }
            $data = app(OpeningBalanceService::class)->validate([
                'cutover_date'=>$input['cutover_date'] ?? null, 'clearing_account_id'=>$input['clearing_account_id'],
                'notes'=>$input['notes'] ?? null,
                'lines'=>[['account_id'=>$input['account_id'], 'partner_id'=>$input['patron_id'] ?? null,
                    'side'=>$input['side'] ?? null, 'amount'=>$input['amount'] ?? null, 'reference'=>$input['reference'] ?? null]],
            ], $plantId);
            $existing = OpeningBalanceBatch::where('plant_id',$plantId)
                ->when(!empty($data['lines'][0]['partner_id']),
                    fn ($q) => $q->where('patron_id',$data['lines'][0]['partner_id']),
                    fn ($q) => $q->whereNull('patron_id')->where('account_id',$data['lines'][0]['account_id']))
                ->orderByDesc('id')->lockForUpdate()->get();
            $old = $existing->first();
            abort_if(($old?->id ?? null) !== (!empty($input['record_id']) ? (int)$input['record_id'] : null)
                || ($old?->version ?? 0) !== (int)$input['version'], 409, 'This balance changed or already exists. Reload and edit its current record.');
            $today = now(); // The request middleware applies the entity's timezone.
            $financialYear = $today->month < 4 ? $today->year - 1 : $today->year;
            $cutoverDate = $old?->cutover_date?->toDateString() ?? sprintf('%04d-04-01', $financialYear);
            if ($data['cutover_date'] !== $cutoverDate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cutover_date' => "The financial year date is fixed at {$cutoverDate} and cannot be changed.",
                ]);
            }
            $before = $old?->toArray();
            foreach ($existing as $prior) $this->retire($prior, $userId, 'REPLACED', $input['reason']);
            return $this->insert($data, $plant, $userId, 'POSTED', $old?->id, $before, $input['reason']);
        });
    }

    public function remove(int $plantId, int $userId, int $id, int $version, string $reason): void
    {
        DB::transaction(function () use ($plantId,$userId,$id,$version,$reason) {
            Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            $record = OpeningBalanceBatch::where('plant_id',$plantId)->whereNotNull('active_key')->lockForUpdate()->findOrFail($id);
            abort_if($record->version !== $version,409,'This balance changed. Reload before removing.');
            $this->retire($record,$userId,'REMOVED',$reason);
        });
    }

    public function legacy(int $plantId)
    {
        return OpeningBalanceBatch::where('plant_id',$plantId)->whereNull('active_key');
    }

    public function convert(int $plantId, int $userId, int $clearingId): void
    {
        DB::transaction(function () use ($plantId,$userId,$clearingId) {
            $plant = Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            $legacy = $this->legacy($plantId)->lockForUpdate()->first();
            if (!$legacy) return;
            $input = $legacy->toArray();
            abort_if($legacy->status==='POSTED' && $legacy->clearing_account_id && (int)$legacy->clearing_account_id!==$clearingId,
                422,'Use the original clearing ledger when converting the posted setup to preserve ledger balances.');
            $input['cutover_date']=$legacy->cutover_date->toDateString();
            $input['clearing_account_id']=$clearingId;
            $data = app(OpeningBalanceService::class)->validate($input,$plantId);
            $keys = array_map(fn ($line) => $this->key($line),$data['lines']);
            abort_if(count($keys)!==count(array_unique($keys)),422,'The old setup contains multiple balances for the same patron. Reconcile these before conversion.');
            $before=$legacy->toArray();
            foreach ($data['lines'] as $line) {
                $single=$data; $single['lines']=[$line];
                $this->insert($single,$plant,$userId,$legacy->status,null,null,'Converted from bulk setup #'.$legacy->id);
            }
            // The latest posted setup is recreated above. Remove all historical netting journals
            // together so the original + reversals cannot leave a residual balance.
            $entries=JournalEntry::where('plant_id',$plantId)->where('ref_id',$legacy->id)
                ->whereIn('ref_module',['opening_balance','opening_balance_reversal'])->get();
            foreach($entries as $entry) $this->softDeleteJournal($entry,$userId);
            $legacy->forceFill(['status'=>'CONVERTED','deleted_by'=>$userId,'deleted_at'=>now(),'version'=>$legacy->version+1])->save();
            $this->audit($legacy,$userId,'CONVERT',$before,null,'Split into independent patron and ledger balances.');
        });
    }

    private function key(array $line): string
    {
        return !empty($line['partner_id']) ? 'P:'.$line['partner_id'] : 'L:'.$line['account_id'];
    }

    private function insert(array $data, Plant $plant, int $userId, string $status, ?int $replaces, ?array $before, string $reason): OpeningBalanceBatch
    {
        $line=$data['lines'][0];
        $record=new OpeningBalanceBatch;
        $record->forceFill($data+[
            'plant_id'=>$plant->id,'patron_id'=>$line['partner_id'],'account_id'=>$line['account_id'],
            'active_key'=>$this->key($line),'replaces_id'=>$replaces,'status'=>$status,
            'version'=>1,'created_by'=>$userId,'updated_by'=>$userId,
        ])->save();
        $entry=null;
        if ($status==='POSTED') {
            $amount=$line['amount']; $date=$record->cutover_date->copy()->subDay()->toDateString();
            $entry=JournalEntry::create([
                'plant_id'=>$plant->id,'entity_id'=>$plant->entity_id,'voucher_type'=>'OPENING',
                'voucher_number'=>'OBI/'.$record->id,'ref_module'=>'opening_balance','ref_id'=>$record->id,
                'voucher_date'=>$date,'posting_date'=>$date,'narration'=>'Individual opening balance. '.$reason,
                'total_debit'=>$amount,'total_credit'=>$amount,'is_status'=>'POSTED','created_by'=>$userId,
            ]);
            $dr=$line['side']==='Dr';
            $entry->lines()->create(['plant_id'=>$plant->id,'account_id'=>$line['account_id'],
                'partner_id'=>$line['partner_id'],'partner_type'=>$line['partner_id']?'Patron':null,
                'debit_amount'=>$dr?$amount:0,'credit_amount'=>$dr?0:$amount,'line_narration'=>$line['reference'] ?? 'Opening balance','created_by'=>$userId]);
            $entry->lines()->create(['plant_id'=>$plant->id,'account_id'=>$data['clearing_account_id'],
                'debit_amount'=>$dr?0:$amount,'credit_amount'=>$dr?$amount:0,'line_narration'=>'Opening balance offset','created_by'=>$userId]);
            $record->update(['journal_entry_id'=>$entry->id,'posted_at'=>now()]);
        }
        $this->audit($record,$userId,$replaces?'REPLACE':'CREATE',$before,$entry,$reason);
        return $record->fresh();
    }

    private function retire(OpeningBalanceBatch $record, int $userId, string $action, string $reason): void
    {
        $before=$record->toArray();
        $entry=$record->journal_entry_id ? JournalEntry::where('plant_id',$record->plant_id)->findOrFail($record->journal_entry_id):null;
        if($entry) { $entry->load('lines'); $this->softDeleteJournal($entry,$userId); }
        $record->forceFill(['active_key'=>null,'status'=>$action,'deleted_at'=>now(),'deleted_by'=>$userId,'version'=>$record->version+1])->save();
        $this->audit($record,$userId,$action,$before,$entry,$reason);
    }

    private function softDeleteJournal(JournalEntry $entry, int $userId): void
    {
        $values=['deleted_at'=>now(),'deleted_by'=>$userId,'is_deleted'=>1];
        // Opening journal deletion is deliberately restricted to this transaction path.
        DB::table('mm_journal_entry_lines')->where('journal_entry_id',$entry->id)->where('plant_id',$entry->plant_id)->whereNull('deleted_at')->update($values);
        DB::table('mm_journal_entries')->where('id',$entry->id)->where('plant_id',$entry->plant_id)->update($values);
    }

    private function audit(OpeningBalanceBatch $record,int $userId,string $action,?array $before,?JournalEntry $entry,string $reason): void
    {
        OpeningBalanceAuditLog::create([
            'plant_id'=>$record->plant_id,'batch_id'=>$record->id,'version'=>$record->version,'action'=>$action,
            'user_id'=>$userId,'actor_name'=>auth()->user()?->name,'reason'=>$reason,'before_values'=>$before,
            'after_values'=>$record->toArray(),'journal'=>$entry?->loadMissing('lines')->toArray(),'created_at'=>now(),
        ]);
    }
}
