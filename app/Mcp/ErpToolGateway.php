<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

namespace App\Mcp;

use App\Http\Controllers\Api\SalesMobile\{LeadController, OrderController};
use App\Models\{CrmActivity, Patron};
use App\Services\CrmLeadService;
use App\Services\Mobile\MobileAccess as Access;
use App\Services\Reports\{ReportPermissions, ReportServiceFactory};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ErpToolGateway
{
    public function execute(string $name, array $arguments): array
    {
        abort_unless(ErpToolCatalog::permits($name), 403);
        $fields = ErpToolCatalog::fields($name);
        if (array_diff(array_keys($arguments), array_keys($fields))) throw ValidationException::withMessages(['arguments'=>'Unknown arguments are not allowed. Plant and user are determined by authentication.']);
        $rules = [];
        foreach ($fields as $field => $spec) {
            $rules[$field] = [$spec[1] ? 'required' : 'sometimes', $spec[0] === 'number' ? 'numeric' : $spec[0]];
            if (isset($spec[2])) $rules[$field][] = Rule::in($spec[2]);
            if (str_ends_with($field, '_id') || $field === 'page') $rules[$field][] = 'min:1';
            if ($field === 'page') $rules[$field][] = 'max:10000';
            if ($field === 'per_page') $rules[$field][] = 'between:1,100';
            if ($spec[0] === 'string') $rules[$field][] = 'max:10000';
        }
        validator($arguments, $rules)->validate();
        $previous = request();
        $input = Request::create($previous->url(), 'GET', $arguments);
        $input->setUserResolver($previous->getUserResolver());
        $input->attributes->add($previous->attributes->all());
        $input->setLaravelSession($previous->session());
        app()->instance('request', $input);
        $success = false;
        try {
            $lead = app(LeadController::class);
            $order = app(OrderController::class);
            $service = app(CrmLeadService::class);
            $response = match ($name) {
                'list_crm_leads'=>$lead->index($input),
                'get_crm_lead'=>$lead->show($arguments['lead_id']),
                'create_crm_lead'=>$lead->store($input, $service),
                'update_crm_lead'=>$lead->update($this->mergeLead($input, $arguments['lead_id']), $arguments['lead_id'], $service),
                'list_follow_ups'=>$lead->followups($input),
                'create_follow_up'=>$this->saveActivity($lead, $input),
                'update_follow_up'=>$this->saveActivity($lead, $this->mergeActivity($input, $arguments['activity_id']), $arguments['activity_id']),
                'complete_follow_up'=>$lead->complete($input, $arguments['activity_id']),
                'list_sales_orders'=>$order->index($input),
                'sales_order_batch_progress'=>$order->show($arguments['order_id']),
                'list_sales_order_batches'=>$order->batches($input, $arguments['order_id']),
                'list_reports'=>['reports'=>array_map(fn ($type)=>['type'=>$type,'label'=>ReportPermissions::REPORTS[$type]], ErpToolCatalog::reports())],
                'run_report'=>$this->report($input),
            };
            $success = true;
            return is_array($response) ? $response : $response->getData(true);
        } finally {
            app()->instance('request', $previous);
            Log::info('erp_mcp_call', ['user_id'=>$previous->user()->id, 'plant_id'=>Access::plantId(), 'tool'=>$name, 'success'=>$success]);
        }
    }

    private function mergeLead(Request $input, int $id): Request
    {
        $model = Access::leads()->findOrFail($id);
        $existing = $model->only(array_keys(ErpToolCatalog::fields('create_crm_lead')));
        foreach (['enquiry_date','expected_purchase_date'] as $date) $existing[$date] = $model->{$date}?->format('Y-m-d');
        $input->replace(array_replace($existing, $input->all()));
        return $input;
    }

    private function mergeActivity(Request $input, int $id): Request
    {
        $model = CrmActivity::where('plant_id', Access::plantId())->whereIn('lead_id', Access::leads()->select('mm_crm_leads.id'))->where('type','!=','System')->findOrFail($id);
        $input->replace(array_replace($model->only(array_keys(ErpToolCatalog::fields('create_follow_up'))), $input->all()));
        return $input;
    }

    private function saveActivity(LeadController $controller, Request $input, ?int $id = null)
    {
        abort_if($input->input('type') === 'System', 422);
        return $controller->saveActivity($input, $id);
    }

    private function report(Request $input): array
    {
        $data = $input->validate(['report'=>['required',Rule::in(ErpToolCatalog::REPORTS)], 'start_date'=>'required|date_format:Y-m-d', 'end_date'=>'required|date_format:Y-m-d|after_or_equal:start_date',
            'patron_id'=>['sometimes','integer',Rule::exists('mm_patrons','id')->where('plant_id',Access::plantId())->whereNull('deleted_at')]]);
        abort_if(Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) > 366, 422);
        $type = $data['report'];
        $params = ['plant_id'=>Access::plantId(), 'start'=>Carbon::parse($data['start_date'])->startOfDay()->toDateTimeString(),
            'end'=>Carbon::parse($data['end_date'])->endOfDay()->toDateTimeString(), 'register_view'=>'summary', 'page'=>$input->integer('page',1), 'per_page'=>$input->integer('per_page',20), 'voucher_type'=>strtoupper($type), 'patron_id'=>$data['patron_id'] ?? null];
        app(ReportPermissions::class)->authorize($type, 'view', $params);
        $result = app(ReportServiceFactory::class)->make($type)->generate($params);
        $result = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $pagination = [];
        foreach (['data','transactions','customers','invoices','all_open_invoices','all_receipts','all_payments'] as $key) {
            if ($key === 'data' && isset($result['pagination']['current_page'])) {
                $pagination['data'] = $result['pagination'];
                continue;
            }
            if (isset($result[$key]) && is_array($result[$key]) && array_is_list($result[$key])) {
                $total = count($result[$key]);
                $perPage = $input->integer('per_page',20); $page = $input->integer('page',1);
                $result[$key] = array_slice($result[$key],($page-1)*$perPage,$perPage);
                $pagination[$key] = ['page'=>$page,'per_page'=>$perPage,'total'=>$total];
            }
        }
        $truncated = [];
        $result = $this->limitNestedLists($result, $truncated);
        return ['report'=>$type, 'plant_id'=>Access::plantId(), 'pagination'=>$pagination, 'truncated_paths'=>$truncated, 'data'=>$result];
    }

    private function limitNestedLists(array $value, array &$truncated, string $path = 'data'): array
    {
        if (array_is_list($value) && count($value) > 100) {
            $value = array_slice($value,0,100);
            $truncated[] = $path;
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = $this->limitNestedLists($item,$truncated,$path.'.'.$key);
        }
        return $value;
    }
}
