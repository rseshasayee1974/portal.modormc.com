<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:29:20 Asia/Calcutta (UTC+05:30)
*/
namespace App\Services;

use App\Models\{CrmLead, CrmDeal, Patron, Plant, ContactType, AddressType};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmLeadService
{
    public function log(CrmLead $lead, string $subject, ?string $description = null): void
    {
        $lead->activities()->create(['plant_id' => $lead->plant_id, 'type' => 'System', 'subject' => $subject, 'description' => $description, 'completed_at' => now()]);
    }

    public function create(int $plantId, array $data): CrmLead
    {
        return DB::transaction(function () use ($plantId, $data) {
            Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            $this->ensureUniqueContact($plantId, $data);
            $lead = CrmLead::create(array_merge($data, ['plant_id' => $plantId]));
            $lead->update(['lead_number' => 'LD/' . now()->format('Y') . '/' . str_pad($lead->id, 5, '0', STR_PAD_LEFT)]);
            $this->log($lead, 'Lead created');
            return $lead;
        });
    }

    public function update(CrmLead $lead, array $data): CrmLead
    {
        return DB::transaction(function () use ($lead, $data) {
            Plant::whereKey($lead->plant_id)->lockForUpdate()->firstOrFail();
            $lead = CrmLead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($lead->converted_at && $data['status'] !== 'Converted') {
                throw ValidationException::withMessages(['status' => 'A converted lead must retain Converted status. Update its deal stage instead.']);
            }
            $this->ensureUniqueContact($lead->plant_id, $data, $lead->id);
            $lead->fill($data);
            $changes = collect($lead->getDirty())->map(fn ($value, $key) => str_replace('_', ' ', $key) . ': ' . ($value ?? 'cleared'))->implode("\n");
            $lead->save();
            if ($changes) $this->log($lead, 'Lead updated', $changes);
            return $lead;
        });
    }

    private function ensureUniqueContact(int $plantId, array $data, ?int $exclude = null): void
    {
        foreach (['phone', 'email'] as $field) {
            if (!empty($data[$field]) && CrmLead::where('plant_id', $plantId)->where($field, $data[$field])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->exists()) {
                throw ValidationException::withMessages([$field => 'An active lead already uses this ' . $field . '. Update that lead or use another contact.']);
            }
        }
    }

    public function convert(CrmLead $lead, ?int $customerId): CrmLead
    {
        return DB::transaction(function () use ($lead, $customerId) {
            Plant::whereKey($lead->plant_id)->lockForUpdate()->firstOrFail();
            $lead = CrmLead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($lead->status !== 'Qualified' || $lead->converted_at) {
                throw ValidationException::withMessages(['customer_id' => 'Only a qualified, unconverted lead can be converted.']);
            }
            if ($customerId) {
                $customer = Patron::where('plant_id', $lead->plant_id)->ofType('Customer')->where('status', 1)->findOrFail($customerId);
            } else {
                $name = $lead->company_name ?: $lead->contact_name;
                $duplicate = Patron::withoutGlobalScope('active_operational_status')->where('plant_id', $lead->plant_id)
                    ->where(function ($query) use ($lead, $name) {
                        $query->where('legal_name', $name)->orWhereHas('contacts', function ($contact) use ($lead) {
                            $contact->where(function ($q) use ($lead) {
                                $q->whereRaw('1 = 0');
                                if ($lead->phone) $q->orWhere('mobile', $lead->phone);
                                if ($lead->email) $q->orWhere('email', $lead->email);
                            });
                        });
                    })->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['customer_id' => 'A patron with this name or contact already exists. Select the existing customer, or activate/update that patron first.']);
                }
                $contactType = ContactType::query()->value('id');
                $addressType = AddressType::query()->value('id');
                if (!$contactType || ($lead->address && !$addressType)) {
                    throw ValidationException::withMessages(['customer_id' => 'Configure contact and address types before creating a customer.']);
                }
                $customer = Patron::create(['plant_id' => $lead->plant_id, 'patron_type' => ['Customer'], 'legal_name' => $name, 'operational_status' => 'active', 'status' => true, 'displayed' => true]);
                $contact = $customer->contacts()->create(['plant_id' => $lead->plant_id, 'contact_type_id' => $contactType, 'name' => $lead->contact_name, 'mobile' => $lead->phone, 'email' => $lead->email, 'is_primary' => true, 'status' => true, 'displayed' => true]);
                if ($lead->address) {
                    $contact->addresses()->create(['plant_id' => $lead->plant_id, 'address_type_id' => $addressType, 'line_1' => mb_substr($lead->address, 0, 200), 'line_2' => mb_substr($lead->address, 200) ?: null, 'is_primary' => true, 'status' => true]);
                }
            }
            $lead->deal()->create(['plant_id' => $lead->plant_id, 'customer_id' => $customer->id, 'name' => $lead->project_name ?: ($lead->company_name ?: $lead->contact_name), 'stage' => CrmDeal::STAGES[0], 'expected_value' => $lead->expected_value, 'expected_close_date' => $lead->expected_purchase_date]);
            $lead->update(['status' => 'Converted', 'customer_id' => $customer->id, 'converted_at' => now()]);
            $this->log($lead, 'Lead converted', 'Customer: ' . $customer->legal_name . '. Sales opportunity created.');
            return $lead;
        });
    }
}
