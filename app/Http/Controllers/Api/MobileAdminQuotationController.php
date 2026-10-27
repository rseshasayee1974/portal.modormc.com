<?php
/*
Author: Cipher
Created: 2026-10-22 13:09:36 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\SalesMobile\QuotationController;
use App\Models\Quotation;
use App\Models\CrmLead;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Database\Eloquent\Builder;

class MobileAdminQuotationController extends QuotationController
{
    public function callAction($method, $parameters)
    {
        $this->authorizeAdministrator();
        Access::authorize('QUOTATION', match ($method) {
            'store', 'createFromLead' => 'CREATE',
            'update', 'submit' => 'UPDATE',
            'destroy' => 'DELETE',
            'review' => 'APPROVE',
            default => 'VIEW',
        });

        return parent::callAction($method, $parameters);
    }

    private function authorizeAdministrator(): void
    {
        $role = request()->attributes->get('mobile_role');
        abort_unless(request()->attributes->get('mobile_system_admin') || in_array(strtoupper((string) $role?->code), [
            'ADMINISTRATOR', 'SUPER_ADMIN', 'PLATFORM_ADMIN', 'SAAS_OWNER',
        ], true), 403, 'An administrator role in the selected plant is required.');
    }

    protected function quotations(): Builder
    {
        $this->authorizeAdministrator();
        Access::authorize('QUOTATION', 'VIEW');
        return Quotation::withoutGlobalScope('plant_id')->where('plant_id', Access::plantId())
            ->whereIn('status', [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT, Quotation::STATUS_ACCEPTED, Quotation::STATUS_REJECTED]);
    }

    protected function quotationLeads(): Builder
    {
        $this->authorizeAdministrator();
        return CrmLead::withoutGlobalScope('plant_id')->where('plant_id', Access::plantId());
    }

    protected function authorizeReview(): void
    {
        $this->authorizeAdministrator();
        Access::authorize('QUOTATION', 'APPROVE');
    }

    protected function detailRelations(): array
    {
        return array_replace(parent::detailRelations(), [
            'patron' => fn ($q) => $q->where('plant_id', Access::plantId()),
            'site' => fn ($q) => $q->where('plant_id', Access::plantId()),
        ]);
    }
}
