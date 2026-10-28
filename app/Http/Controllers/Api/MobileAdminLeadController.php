<?php
/*
Author: Cipher
Created: 2026-10-22 12:48:30 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\SalesMobile\LeadController;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\CrmLeadService;
use Illuminate\Http\Request;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Database\Eloquent\Builder;

class MobileAdminLeadController extends LeadController
{
    protected function leads(): Builder
    {
        $role = request()->attributes->get('mobile_role');
        abort_unless(request()->attributes->get('mobile_system_admin') || in_array(strtoupper((string) $role?->code), [
            'ADMINISTRATOR', 'SUPER_ADMIN', 'PLATFORM_ADMIN', 'SAAS_OWNER',
        ], true), 403, 'An administrator role in the selected plant is required.');

        return CrmLead::withoutGlobalScope('plant_id')->where('plant_id', Access::plantId());
    }

    protected function leadOwnerId(?CrmLead $lead): ?int
    {
        // Apply the same administrator gate used by list/detail before accepting assignment.
        $this->leads();
        if (request()->has('assigned_to') && (request()->input('assigned_to') === null || request()->input('assigned_to') === '')) {
            if ($lead?->assigned_to !== null) {
                Access::authorize('CRM_LEAD', 'ASSIGN');
            }
            return null;
        }

        $owner = request()->input('assigned_to', $lead?->assigned_to ?? request()->user()->id);
        if ($owner === null || $owner === '') {
            return null;
        }
        $owner = (int) $owner;

        if ($owner !== (int) ($lead?->assigned_to ?? request()->user()->id)) {
            Access::authorize('CRM_LEAD', 'ASSIGN');
        }
        $user = User::where('is_active', 1)->find($owner);
        abort_unless($user && Access::plants($user)->whereKey(Access::plantId())->exists(), 422, 'Owner must belong to this plant.');
        return $owner;
    }

    public function allowedStatuses(?CrmLead $lead = null): array
    {
        if ($lead?->converted_at) {
            return [CrmLead::STATUS_CONVERTED];
        }

        return [
            CrmLead::STATUS_NEW,
            CrmLead::STATUS_CONTACTED,
            CrmLead::STATUS_QUALIFIED,
            CrmLead::STATUS_APPROVED,
            CrmLead::STATUS_UNQUALIFIED,
        ];
    }

    protected function activities(): Builder
    {
        return CrmActivity::withoutGlobalScope('plant_id')
            ->where('plant_id', Access::plantId())
            ->where('type', '!=', 'System');
    }

    public function store(Request $request, CrmLeadService $service)
    {
        $this->leads();
        return parent::store($request, $service);
    }

    public function statuses(?int $lead = null)
    {
        // Also guard the generic status catalog, which does not query a lead.
        $this->leads();
        return parent::statuses($lead);
    }
}