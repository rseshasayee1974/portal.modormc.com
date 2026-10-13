<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

namespace App\Mcp;

use App\Models\{CrmLead, CrmActivity};
use App\Services\Mobile\MobileAccess as Access;
use App\Services\Reports\ReportPermissions;

class ErpToolCatalog
{
    public const REPORTS = ['sales_register', 'purchase_register', 'customer_outstanding', 'sales', 'batching_schedule', 'production_batch', 'inventory_stock', 'patron'];

    public static function definitions(): array
    {
        return [
            'list_crm_leads'=>['CRM_LEAD','VIEW','Find assigned CRM leads with search, status and pagination.',true],
            'get_crm_lead'=>['CRM_LEAD','VIEW','Read one accessible lead and its activities.',true],
            'create_crm_lead'=>['CRM_LEAD','CREATE','Create a CRM lead using the existing validation and duplicate checks.',false],
            'update_crm_lead'=>['CRM_LEAD','UPDATE','Update selected fields of an accessible CRM lead.',false],
            'list_follow_ups'=>['CRM_LEAD','VIEW','List today, upcoming, overdue, completed or all accessible follow-ups.',true],
            'create_follow_up'=>['CRM_LEAD','UPDATE','Schedule a call, visit, meeting or note against an accessible lead.',false],
            'update_follow_up'=>['CRM_LEAD','UPDATE','Edit selected fields of an incomplete accessible follow-up.',false],
            'complete_follow_up'=>['CRM_LEAD','UPDATE','Mark an accessible follow-up completed; repeated completion is safe.',false],
            'list_sales_orders'=>['SALES_ORDER','VIEW','Find accessible sales orders with pagination.',true],
            'sales_order_batch_progress'=>['SALES_ORDER','VIEW','Read ordered volume, completed/cancelled batches, dispatch and delivery progress.',true],
            'list_sales_order_batches'=>['SALES_ORDER','VIEW','List batches against one accessible sales order.',true],
            'list_reports'=>['REPORT','VIEW','List reports this user may view in the selected plant.',true],
            'run_report'=>['REPORT','VIEW','Generate a permitted report with bounded dates and paginated rows. Totals cover the complete report.',true],
        ];
    }

    public static function permits(string $name): bool
    {
        $definition = self::definitions()[$name];
        return $definition[0] === 'REPORT' ? self::reports() !== [] : Access::permits($definition[0], $definition[1]);
    }

    public static function reports(): array
    {
        return array_values(array_filter(self::REPORTS, fn ($type) => app(ReportPermissions::class)->allows($type, 'view', ['register_view'=>'summary'])));
    }

    public static function fields(string $name): array
    {
        $page = ['page'=>['integer',false], 'per_page'=>['integer',false]];
        $lead = [
            'contact_name'=>['string',true], 'company_name'=>['string',false], 'phone'=>['string',false], 'email'=>['string',false],
            'enquiry_date'=>['string',true], 'source'=>['string',true,CrmLead::SOURCES], 'priority'=>['string',true,CrmLead::PRIORITIES],
            'status'=>['string',true,['New','Contacted','Qualified','Unqualified']], 'expected_value'=>['number',true],
            'assigned_to'=>['integer',false], 'address'=>['string',false], 'project_name'=>['string',false], 'requirement'=>['string',false],
            'estimated_quantity'=>['number',false], 'delivery_location'=>['string',false], 'expected_purchase_date'=>['string',false],
            'remarks'=>['string',false], 'lost_reason'=>['string',false],
        ];
        $activity = ['lead_id'=>['integer',true], 'type'=>['string',true,array_values(array_diff(CrmActivity::TYPES,['System']))], 'subject'=>['string',true], 'description'=>['string',false], 'due_at'=>['string',false]];
        $optional = fn ($fields) => array_map(fn ($field) => [$field[0],false,...array_slice($field,2)], $fields);
        return match ($name) {
            'list_crm_leads'=> $page + ['search'=>['string',false], 'status'=>['string',false,CrmLead::STATUSES]],
            'get_crm_lead'=>['lead_id'=>['integer',true]],
            'create_crm_lead'=>$lead,
            'update_crm_lead'=>['lead_id'=>['integer',true]] + $optional($lead),
            'list_follow_ups'=>$page + ['view'=>['string',false,['today','upcoming','overdue','completed','all']]],
            'create_follow_up'=>$activity,
            'update_follow_up'=>['activity_id'=>['integer',true]] + $optional($activity),
            'complete_follow_up'=>['activity_id'=>['integer',true], 'description'=>['string',false]],
            'list_sales_orders'=>$page + ['search'=>['string',false], 'customer_id'=>['integer',false]],
            'sales_order_batch_progress'=>['order_id'=>['integer',true]],
            'list_sales_order_batches'=>['order_id'=>['integer',true]] + $page,
            'run_report'=>['report'=>['string',true,self::REPORTS], 'start_date'=>['string',true], 'end_date'=>['string',true], 'patron_id'=>['integer',false]] + $page,
            default=>[],
        };
    }
}
