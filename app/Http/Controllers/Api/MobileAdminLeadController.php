<?php
/*
Author: Cipher
Created: 2026-10-22 12:48:30 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\SalesMobile\LeadController;
use App\Models\CrmLead;
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

    protected function leadOwnerId(?CrmLead $lead): int
    {
        return (int) ($lead?->assigned_to ?? request()->user()->id);
    }

    public function statuses(?int $lead = null)
    {
        // Also guard the generic status catalog, which does not query a lead.
        $this->leads();
        return parent::statuses($lead);
    }
}
