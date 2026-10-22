<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 12:29:20 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\AddressType;
use App\Models\ContactType;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Site;
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
            if (! empty($data['mix_design_id'])) {
                $data['requirement'] = null;
            }
            $lead = new CrmLead(array_merge($data, ['plant_id' => $plantId]));
            $lead->save();
            $lead->update(['lead_number' => 'LD/'.now()->format('Y').'/'.str_pad($lead->id, 5, '0', STR_PAD_LEFT)]);
            $this->log($lead, 'Lead created');
            if (in_array($lead->status, CrmLead::QUALIFYING_STATUSES, true)) {
                $this->prepareCustomerAndSite($lead);
            }

            return $lead;
        });
    }

    public function update(CrmLead $lead, array $data): CrmLead
    {
        return DB::transaction(function () use ($lead, $data) {
            Plant::whereKey($lead->plant_id)->lockForUpdate()->firstOrFail();
            $lead = CrmLead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($lead->converted_at && ($data['status'] ?? $lead->status) !== CrmLead::STATUS_CONVERTED) {
                throw ValidationException::withMessages(['status' => 'A converted lead must retain Converted status. Update its deal stage instead.']);
            }
            $this->ensureUniqueContact($lead->plant_id, $data, $lead->id);
            if (array_key_exists('mix_design_id', $data) && ($data['mix_design_id'] !== null || $lead->mix_design_id !== null)) {
                $data['requirement'] = null;
            }
            $lead->fill($data);
            if ((array_key_exists('phone', $data) || array_key_exists('email', $data)) && ! filled($lead->phone) && ! filled($lead->email)) {
                throw ValidationException::withMessages(['phone' => 'A lead must retain a phone or email.', 'email' => 'A lead must retain a phone or email.']);
            }
            if ((array_key_exists('status', $data) || array_key_exists('lost_reason', $data)) && $lead->status === CrmLead::STATUS_UNQUALIFIED && ! filled($lead->lost_reason)) {
                throw ValidationException::withMessages(['lost_reason' => 'A reason is required for an unqualified lead.']);
            }
            $changes = collect($lead->getDirty())->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.($value ?? 'cleared'))->implode("\n");
            $lead->save();
            if (array_key_exists('status', $data) && in_array($lead->status, CrmLead::QUALIFYING_STATUSES, true)) {
                $this->prepareCustomerAndSite($lead);
            }
            if ($changes) {
                $this->log($lead, 'Lead updated', $changes);
            }

            return $lead;
        });
    }

    private function ensureUniqueContact(int $plantId, array $data, ?int $exclude = null): void
    {
        foreach (['phone', 'email'] as $field) {
            if (! empty($data[$field]) && CrmLead::where('plant_id', $plantId)->where($field, $data[$field])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->exists()) {
                throw ValidationException::withMessages([$field => 'An active lead already uses this '.$field.'. Update that lead or use another contact.']);
            }
        }
    }

    public function convert(CrmLead $lead, ?int $customerId, bool $forQuotation = false): CrmLead
    {
        return DB::transaction(function () use ($lead, $customerId, $forQuotation) {
            Plant::whereKey($lead->plant_id)->lockForUpdate()->firstOrFail();
            $lead = CrmLead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $allowedStatuses = $forQuotation ? CrmLead::QUOTATION_CONVERTIBLE_STATUSES : CrmLead::QUALIFYING_STATUSES;
            if (! in_array($lead->status, $allowedStatuses, true) || $lead->converted_at) {
                throw ValidationException::withMessages(['customer_id' => 'Only an eligible, unconverted lead can be converted.']);
            }
            $customerId ??= $lead->customer_id;
            if ($customerId) {
                $customer = Patron::where('plant_id', $lead->plant_id)->ofType('Customer')->where('status', 1)->findOrFail($customerId);
            } else {
                $customer = $this->createCustomer($lead);
            }
            $lead->deal()->create(['plant_id' => $lead->plant_id, 'customer_id' => $customer->id, 'name' => $lead->delivery_location ?: ($lead->company_name ?: $lead->contact_name), 'stage' => CrmDeal::STAGES[0], 'expected_value' => $lead->expected_value, 'expected_close_date' => $lead->expected_purchase_date]);
            $lead->update(['status' => CrmLead::STATUS_CONVERTED, 'customer_id' => $customer->id, 'converted_at' => now()]);
            $this->log($lead, 'Lead converted', 'Customer: '.$customer->legal_name.'. Sales opportunity created.');

            return $lead;
        });
    }

    public function prepareCustomerAndSite(CrmLead $lead): void
    {
        $customerId = DB::transaction(function () use ($lead) {
            Plant::whereKey($lead->plant_id)->lockForUpdate()->firstOrFail();
            // Read the persisted customer link only after concurrent preparation has finished.
            $lead = CrmLead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $name = trim($lead->company_name ?: $lead->contact_name);
            $patrons = Patron::withoutGlobalScope('plant_id')->withoutGlobalScope('active_operational_status')
                ->where('plant_id', $lead->plant_id);
            $customers = (clone $patrons)->ofType('Customer');
            $customer = $lead->customer_id
                ? (clone $customers)->find($lead->customer_id)
                : (clone $customers)->whereRaw('LOWER(TRIM(legal_name)) = ?', [mb_strtolower($name)])->orderBy('id')->lockForUpdate()->first();
            if ($customer && (! $customer->status || $customer->operational_status !== 'active')) {
                throw ValidationException::withMessages(['customer_id' => 'The existing customer must be active before qualifying this lead.']);
            } 
            if ($lead->customer_id && ! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'The linked customer is not available in this plant.']);
            }
            if (! $customer) {
                $existing = (clone $patrons)->withTrashed()->whereRaw('LOWER(TRIM(legal_name)) = ?', [mb_strtolower($name)])
                    ->orderBy('id')->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->trashed() || ! $existing->status || $existing->operational_status !== 'active') {
                        throw ValidationException::withMessages(['customer_id' => 'Restore or activate the existing patron before qualifying this lead.']);
                    }
                    // Patron names are unique per plant; retain existing categories when adding Customer.
                    $existing->update(['patron_type' => array_values(array_unique(array_merge($existing->patron_type ?? [], ['Customer'])))]);
                    $customer = $existing;
                }
            }
            $customer ??= $this->createCustomer($lead);
            if ((int) $lead->customer_id !== (int) $customer->id) {
                $lead->update(['customer_id' => $customer->id]);
            }
            $siteName = mb_substr(trim($lead->delivery_location ?: ($lead->delivery_location ?: $customer->legal_name)), 0, 255);
            $site = Site::withoutGlobalScope('plant_id')->where('plant_id', $lead->plant_id)
                ->where('type', 'unloading')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($siteName)])->orderBy('id')->lockForUpdate()->first();
            if ($site) {
                $patronIds = array_map('intval', $site->patron_id ?? []);
                if (! in_array((int) $customer->id, $patronIds, true)) {
                    $site->update(['patron_id' => array_values(array_unique([...$patronIds, (int) $customer->id]))]);
                }
            } else {
                $location = $lead->delivery_location ?: $lead->address;
                Site::create([
                    'plant_id' => $lead->plant_id, 'patron_id' => [$customer->id], 'name' => $siteName,
                    'site_address_1' => $location ? mb_substr($location, 0, 500) : null,
                    'site_address_2' => $location ? (mb_substr($location, 500, 500) ?: null) : null,
                    'type' => 'unloading', 'status' => 'Active', 'is_restricted' => false,
                ]);
            }

            return $customer->id;
        });
        $lead->setAttribute('customer_id', $customerId);
        $lead->syncOriginalAttribute('customer_id');
        $lead->unsetRelation('customer');
    }

    private function createCustomer(CrmLead $lead): Patron
    {
        $name = trim($lead->company_name ?: $lead->contact_name);
        $duplicate = Patron::withoutGlobalScope('active_operational_status')->where('plant_id', $lead->plant_id)
            ->ofType('Customer')
            ->where(function ($query) use ($lead, $name) {
                $query->where('legal_name', $name)->orWhereHas('contacts', function ($contact) use ($lead) {
                    $contact->where(function ($q) use ($lead) {
                        $q->whereRaw('1 = 0');
                        if ($lead->phone) {
                            $q->orWhere('mobile', $lead->phone);
                        }
                        if ($lead->email) {
                            $q->orWhere('email', $lead->email);
                        }
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
        return $customer;
    }
}
