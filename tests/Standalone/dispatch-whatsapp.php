<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string) $e); exit(1); });

use App\Helpers\DateTimeHelper;
use App\Models\{Batch, Contact, Dispatch, DispatchStatus, Machine, MixDesign, Patron, Personnel, SalesOrder, Site};
use App\Notifications\DispatchCompletedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\{Auth, Cache, DB, Http, Schema};
use App\Models\WhatsAppMessage;
use App\Services\DispatchWhatsAppService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

config(['database.default' => 'dispatch_whatsapp_test', 'database.connections.dispatch_whatsapp_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'session.driver' => 'array']);
DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY, plant_id INTEGER, batch_id INTEGER, unload_site_id INTEGER, dispatch_status TEXT, updated_at TEXT, deleted_at TEXT, whatsapp_submitted_at TEXT, whatsapp_contact TEXT, whatsapp_template TEXT)');
DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, plant_id INTEGER, status INTEGER, deleted_at TEXT)');
for ($id = 1; $id <= 10; $id++) {
    DB::table('mm_batches')->insert(['id' => $id, 'plant_id' => 1, 'status' => Batch::STATUS_COMPLETED]);
    DB::table('mm_dispatches')->insert(['id' => 1000 + $id, 'batch_id' => $id, 'plant_id' => 1, 'unload_site_id' => 7, 'dispatch_status' => 'Delivered']);
}
// Extra dispatch for the same batch must not count twice.
DB::table('mm_dispatches')->insert(['id' => 2000, 'batch_id' => 1, 'plant_id' => 1, 'unload_site_id' => 7, 'dispatch_status' => 'Invoiced']);
foreach ([
    [11, 1, Batch::STATUS_DISPATCHED, 7, 'In Transit', null, null],
    [12, 1, Batch::STATUS_CANCELLED, 7, 'Cancelled', null, null],
    [13, 1, Batch::STATUS_COMPLETED, 8, 'Delivered', null, null],
    [14, 2, Batch::STATUS_COMPLETED, 7, 'Delivered', null, null],
    [15, 1, Batch::STATUS_COMPLETED, 7, 'Delivered', '2026-10-01', null],
    [16, 1, Batch::STATUS_COMPLETED, 7, 'Delivered', null, '2026-10-01'],
    [17, 1, Batch::STATUS_COMPLETED, 7, 'Cancelled', null, null],
] as [$id, $plant, $status, $site, $dispatchStatus, $batchDeleted, $dispatchDeleted]) {
    DB::table('mm_batches')->insert(['id' => $id, 'plant_id' => $plant, 'status' => $status, 'deleted_at' => $batchDeleted]);
    DB::table('mm_dispatches')->insert(['id' => 1000 + $id, 'batch_id' => $id, 'plant_id' => $plant,
        'unload_site_id' => $site, 'dispatch_status' => $dispatchStatus, 'deleted_at' => $dispatchDeleted]);
}
DB::table('mm_dispatches')->insert(['id' => 99, 'plant_id' => 1, 'whatsapp_submitted_at' => '2026-10-05 12:00:00', 'whatsapp_contact' => '919000000099', 'whatsapp_template' => 'modormc_dispatch']);
$migration = require __DIR__ . '/../../database/migrations/2026_10_06_130000_create_whatsapp_messages_table.php';
$migration->up();
$migration->up(); // Re-running must not duplicate migrated legacy submissions.
checkWhatsApp(WhatsAppMessage::where('origin_id', 99)->count() === 1, 'Legacy submission must be preserved once');
checkWhatsApp(!Schema::hasColumn('mm_dispatches', 'whatsapp_submitted_at') && !Schema::hasColumn('mm_dispatches', 'whatsapp_contact') && !Schema::hasColumn('mm_dispatches', 'whatsapp_template'), 'Legacy dispatch columns must be removed');
Http::preventStrayRequests();

function checkWhatsApp(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

DateTimeHelper::inTimezone('Asia/Kolkata', function () {
    $mix = (new MixDesign(['design_name' => 'M25']))->setRelation('concreteGrade', null);
    $site = new Site(['name' => 'Tower A', 'site_address_1' => '12 Main Street', 'city' => 'Chennai']);
    $order = (new SalesOrder(['total_qty' => 100, 'produced_qty' => 25.125]))
        ->setRelation('site', $site)->setRelation('mixDesign', $mix);
    $customer = (new Patron(['legal_name' => 'Example & Sons']))->setRelation('contacts', new Collection([
        new Contact(['mobile' => '9000000001', 'is_primary' => false]),
        new Contact(['mobile' => '+91 90000 00002', 'is_primary' => true]),
    ]));
    $dispatch = (new Dispatch([
        'dispatch_time' => '2026-10-06 06:15:00', 'delivered_qty' => 6.125, 'dispatch_no' => '42',
        'plant_id' => 1, 'unload_site_id' => 7,
    ]))->setRelation('customer', $customer)->setRelation('workOrder', null)
        ->setRelation('mixDesign', new MixDesign(['design_name' => 'Wrong dispatch mix']))
        ->setRelation('unloadSite', new Site(['name' => 'Wrong dispatch site']))
        ->setRelation('truck', new Machine(['registration' => 'TN 01 AB 1234']))
        ->setRelation('driver', new Personnel(['first_name' => 'Arun', 'last_name' => 'Kumar']))
        ->setRelation('batch', (new Batch(['batch_no' => 15, 'batch_size' => 6.25]))->setRelation('salesOrder', $order))
        ->setRelation('status', new DispatchStatus(['receiver_name' => 'Site Receiver', 'receive_mobile' => '9000000003']));
    // Some dispatch records have a numeric status column as well as the relationship.
    $dispatch->status = 1;

    $expected = implode("\n", [
        '📅 Date: 06-10-2026',
        '🏢 Party Name: Example & Sons',
        '📍 Site Location: Tower A, 12 Main Street, Chennai',
        '📦 Order Qty: 100.00 m³',
        '🚚 Dispatch Qty: 6.125 m³',
        '📊 Balance Qty: 74.875 m³',
        '🏗️ Mix: M25',
        '👷 Site Supervisor: Site Receiver',
        '📞 Site Contact: 9000000003',
        '⏰ Dispatch Time: 06:15 AM',
        '👨‍🔧 Driver: Arun Kumar',
        '🚛 Vehicle No: TN 01 AB 1234',
        '🔢 Batch No: 15',
        '📋 Load No: 10',
        '',
        '*Thank you.*',
    ]);
    checkWhatsApp((new DispatchCompletedNotification($dispatch))->toWhatsAppMessage() === $expected, 'Template must use the batch-linked sales order, site and mix');
    $dispatch->unload_site_id = 8;
    checkWhatsApp((new DispatchCompletedNotification($dispatch))->toWhatsAppTemplateParameters()[13] === '1', 'Load count must be specific to unload site');
    $dispatch->unload_site_id = 9;
    checkWhatsApp((new DispatchCompletedNotification($dispatch))->toWhatsAppTemplateParameters()[13] === '0', 'Site without completed batches must show zero');
    $dispatch->unload_site_id = null;
    checkWhatsApp((new DispatchCompletedNotification($dispatch))->toWhatsAppTemplateParameters()[13] === 'N/A', 'Missing unload site must not count other sites');
    $dispatch->unload_site_id = 7;
    $url = $dispatch->getWhatsAppUrl();
    checkWhatsApp(str_starts_with($url, 'https://wa.me/919000000002?text='), 'Primary customer recipient incorrect');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    checkWhatsApp($query['text'] === $expected, 'WhatsApp URL must preserve Unicode, newlines, and punctuation');

    // Provider calls are mocked; this test never sends a real WhatsApp message.
    config(['services.tendigit.license_number' => 'test-license', 'services.tendigit.api_key' => 'test-key',
        'services.tendigit.dispatch_template' => 'modormc_dispatch']);
    DB::table('mm_dispatches')->insert(['id' => 1]);
    session(['active_entity_id' => 11]);
    $sender = new App\Models\User;
    $sender->id = 7;
    Auth::setUser($sender);
    $dispatch->plant_id = 1;
    $dispatch->id = 1;
    $dispatch->exists = true;
    $dispatch->batch->status = Batch::STATUS_DISPATCHED;
    $dispatch->syncOriginal();
    $service = new DispatchWhatsAppService;
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(['app.tendigit.in/*' => Http::response(['status' => 'success', 'message' => 'Submitted'], 200)]);
    $service->send($dispatch);
    checkWhatsApp(Http::recorded(function ($request) {
        $params = $request->data();
        checkWhatsApp($params['Contact'] === '919000000002' && $params['Template'] === 'modormc_dispatch', 'API contact or template incorrect');
        checkWhatsApp($params['LicenseNumber'] === 'test-license' && $params['APIKey'] === 'test-key', 'Configured credentials missing');
        checkWhatsApp(explode(',', $params['Param']) === [
            '06-10-2026', 'Example & Sons', 'Tower A; 12 Main Street; Chennai', '100.00', '6.125', '74.875',
            'M25', 'Site Receiver', '9000000003', '06:15 AM', 'Arun Kumar', 'TN 01 AB 1234', '15', '10',
        ], 'Exactly fourteen positional parameters are required, including comma-safe addresses');
        return $request->method() === 'GET';
    })->count() === 1, 'API request not recorded');
    $submission = WhatsAppMessage::where('origin', 'dispatch')->where('origin_id', 1)->firstOrFail();
    checkWhatsApp($submission->status === 'submitted' && $submission->submitted_at !== null && $submission->category === 'dispatch_details', 'Submission not persisted');
    checkWhatsApp($submission->plant_id === 1 && $submission->entity_id === 11 && $submission->created_by === 7 && $submission->updated_by === 7, 'Submission scope/audit user incorrect');
    checkWhatsApp(count($submission->parameters) === 14 && $submission->response_status === 200 && $submission->provider_response !== null && $submission->created_at !== null && $submission->updated_at !== null, 'Parameters, provider result or timestamps missing');
    checkWhatsApp(DB::table('mm_dispatches')->where('id', 1)->value('updated_at') === null, 'WhatsApp tracking must not update dispatch data');
    try { $service->send($dispatch); throw new RuntimeException('Duplicate send accepted'); }
    catch (HttpException $e) { checkWhatsApp($e->getStatusCode() === 409, 'Duplicate must return 409'); }
    checkWhatsApp(Http::recorded()->count() === 1, 'Duplicate request reached provider');

    foreach ([Http::response(['status' => 'error', 'message' => 'Invalid template'], 200), Http::response('Unavailable', 503),
        Http::response(['status' => false], 200), Http::response('', 200), Http::failedConnection()] as $attempt => $failedResponse) {
        $dispatch->id = 100 + $attempt;
        Http::swap(new Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['app.tendigit.in/*' => $failedResponse]);
        try { $service->send($dispatch); throw new RuntimeException('Provider failure accepted'); }
        catch (ValidationException $e) { checkWhatsApp(isset($e->errors()['whatsapp']), 'Provider errors must be readable'); }
        $failure = WhatsAppMessage::where('origin_id', $dispatch->id)->latest('id')->firstOrFail();
        checkWhatsApp(in_array($failure->status, ['failed', 'unknown']) && $failure->submitted_at === null && $failure->error_message !== null, 'Failed/uncertain attempts must be logged correctly');
        if ($failure->status === 'unknown') {
            try { $service->send($dispatch); throw new RuntimeException('Uncertain send retried automatically'); }
            catch (HttpException $e) { checkWhatsApp($e->getStatusCode() === 409, 'Uncertain sends require provider review'); }
        }
        $lock = Cache::lock('dispatch-whatsapp:' . $dispatch->id, 60);
        checkWhatsApp($lock->get(), 'Request lock must release after failure');
        $lock->release();
        if ($attempt === 0) {
            Http::swap(new Illuminate\Http\Client\Factory);
            Http::preventStrayRequests();
            Http::fake(['app.tendigit.in/*' => Http::response([
                'status' => 'success', 'message' => 'Submitted', 'echoed_credentials' => 'test-key test-license',
            ], 200)]);
            $service->send($dispatch);
            $retry = WhatsAppMessage::where('origin_id', $dispatch->id)->latest('id')->firstOrFail();
            checkWhatsApp($failure->fresh()->status === 'failed' && $retry->status === 'submitted'
                && WhatsAppMessage::where('origin_id', $dispatch->id)->count() === 2, 'Retry must preserve failed attempt history');
            checkWhatsApp(!str_contains($retry->provider_response, 'test-key') && !str_contains($retry->provider_response, 'test-license'), 'Provider logs must redact credentials');
        }
    }
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake();
    $dispatch->id = 500;
    config(['services.tendigit.api_key' => null]);
    try { $service->send($dispatch); throw new RuntimeException('Missing credentials accepted'); }
    catch (ValidationException $e) { checkWhatsApp(isset($e->errors()['whatsapp']), 'Missing config error incorrect'); }
    checkWhatsApp(Http::recorded()->isEmpty(), 'Missing credentials reached provider');

    $controller = new class extends App\Http\Controllers\DispatchController {
        protected function authorizeModule(string $action, ?string $module = null): void
        {
            checkWhatsApp($action === 'whatsapp', 'Sending must require the WhatsApp permission');
        }
    };
    $spy = new class extends DispatchWhatsAppService {
        public int $calls = 0;
        public function send(Dispatch $dispatch): void { $this->calls++; }
    };
    session(['active_plant_id' => 1]);
    $dispatch->plant_id = 2;
    try { $controller->sendWhatsApp($dispatch, $spy); throw new RuntimeException('Cross-plant send accepted'); }
    catch (HttpException $e) { checkWhatsApp($e->getStatusCode() === 403 && $spy->calls === 0, 'Cross-plant send reached provider'); }
    $dispatch->plant_id = 1;
    checkWhatsApp($controller->sendWhatsApp($dispatch, $spy)->getStatusCode() === 200 && $spy->calls === 1, 'Authorized send did not reach service');

    $dispatch->setRelation('mixDesign', null)->setRelation('unloadSite', null);
    $order->produced_qty = 110;
    $dispatch->delivered_qty = 0; // Existing accessor falls back to the batch size.
    $message = (new DispatchCompletedNotification($dispatch))->toWhatsAppMessage();
    checkWhatsApp(str_contains($message, '🏗️ Mix: M25') && str_contains($message, '📍 Site Location: Tower A'), 'Batch-linked mix/site incorrect');
    checkWhatsApp(str_contains($message, '📊 Balance Qty: 0.00 m³') && str_contains($message, '🚚 Dispatch Qty: 6.25 m³'), 'Balance floor or batch quantity fallback incorrect');

    foreach (['customer', 'workOrder', 'mixDesign', 'unloadSite', 'truck', 'driver', 'batch', 'status'] as $relation) {
        $dispatch->setRelation($relation, null);
    }
    $dispatch->dispatch_time = null;
    $message = (new DispatchCompletedNotification($dispatch))->toWhatsAppMessage();
    checkWhatsApp(str_contains($message, '👷 Site Supervisor: N/A') && str_contains($message, '📞 Site Contact: N/A'), 'Missing receiver fallback incorrect');
    checkWhatsApp($dispatch->getWhatsAppUrl() === null, 'Missing recipient must not create a WhatsApp URL');
});

$migration->down();
$unconfigured = new Dispatch(['id' => 1]);
try { (new DispatchWhatsAppService)->send($unconfigured); throw new RuntimeException('Missing tracking schema accepted'); }
catch (ValidationException $e) { checkWhatsApp(isset($e->errors()['whatsapp']), 'Missing schema must return a configuration error'); }
checkWhatsApp(Http::recorded()->isEmpty(), 'Missing schema reached provider');
echo "Dispatch WhatsApp template, API parameter order, receiver fields, submission tracking, duplicate prevention, provider failures and fallback checks passed.\n";
