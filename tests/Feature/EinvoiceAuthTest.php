<?php
/*
Author: ragul-onemodo
Created: 2026-11-01 14:08:17 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\EinvoiceAuth;
use App\Services\EInvoiceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class EinvoiceAuthTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('irnCancellationCases')]
    public function test_irn_cancellation_saves_only_a_confirmed_gateway_result(string $provider, string $case): void
    {
        config([
            'database.default' => 'irn_cancel_test',
            'database.connections.irn_cancel_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'services.einvoice.provider' => $provider,
            'services.alankit.environment' => 'sandbox',
            'services.alankit.sandbox_cancel_url' => 'https://gateway.test/Invoice/Cancel',
        ]);
        DB::statement('CREATE TABLE mm_einvoice_invoice_rel (id INTEGER PRIMARY KEY, invoice_id INTEGER, einv_irn TEXT, einv_status TEXT, einv_ack_date TEXT, einv_cancel_at TEXT, status INTEGER, modified TEXT, modified_by INTEGER)');
        DB::statement('CREATE TABLE mm_ewaybill_details (id INTEGER PRIMARY KEY, plant_id INTEGER, origin_id INTEGER, generation_type TEXT, ewaybill_no TEXT, ewaybill_status TEXT)');
        DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY, plant_id INTEGER, batch_id INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_dispatch_statuses (id INTEGER PRIMARY KEY, dispatch_id INTEGER, invoice_id INTEGER, deleted_at TEXT)');
        $irn = str_repeat('a', 64);
        $cancelledAt = now()->toDateTimeString();
        DB::table('mm_einvoice_invoice_rel')->insert([
            'id' => 1, 'invoice_id' => 42, 'einv_irn' => $irn, 'einv_status' => 'ACT',
            'einv_ack_date' => now()->subHours($case === 'expired' ? 25 : 1)->toDateTimeString(),
        ]);
        if ($case === 'active e-way bill') {
            DB::table('mm_dispatches')->insert(['id' => 1, 'plant_id' => 7, 'batch_id' => 123]);
            DB::table('mm_dispatch_statuses')->insert(['id' => 1, 'dispatch_id' => 1, 'invoice_id' => 42]);
            DB::table('mm_ewaybill_details')->insert([
                'plant_id' => 7, 'origin_id' => 123, 'generation_type' => 'batch',
                'ewaybill_no' => '123456789012', 'ewaybill_status' => 'ACT',
            ]);
        }
        $record = \App\Models\EinvoiceInvoiceRel::findOrFail(1);
        $invoice = new \App\Models\Invoice(['plant_id' => 7]);
        $invoice->id = 42;
        $plant = (new \App\Models\Plant(['gstin' => 'test-gstin']))->setRelation('entity', null);
        $invoice->setRelation('plant', $plant)->setRelation('einvoiceRelation', $record);
        $appKey = str_repeat('k', 32);
        $sek = str_repeat('s', 32);
        $auth = new EinvoiceAuth([
            'auth_token' => 'test-token', 'user_name' => 'test-user', 'app_key' => base64_encode($appKey),
            'sek_key' => base64_encode(openssl_encrypt($sek, 'aes-256-ecb', $appKey, OPENSSL_RAW_DATA)),
        ]);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('authenticate')->with($plant, 1, $provider)
            ->times(in_array($case, ['expired', 'active e-way bill'], true) ? 0 : 1)->andReturn($auth);
        $service->baseUrl = 'https://gateway.test';
        $service->email = 'test@example.test';
        $service->shouldReceive('getGatewayConfig')->andReturn([]);

        Http::preventStrayRequests();
        Http::fake(['https://gateway.test/*' => function ($request) use ($provider, $case, $sek, $irn, $cancelledAt) {
            $path = $provider === 'alankit' ? '/Invoice/Cancel' : '/einvoice/type/CANCEL/version/V1_03';
            $this->assertSame($path, parse_url($request->url(), PHP_URL_PATH));
            $payload = $provider === 'alankit'
                ? json_decode(openssl_decrypt(base64_decode($request['Data']), 'aes-256-ecb', $sek, OPENSSL_RAW_DATA), true)
                : $request->data();
            $this->assertSame(['Irn' => $irn, 'CnlRsn' => '2', 'CnlRem' => 'Wrong details'], $payload);
            $data = ['Irn' => $irn, 'CancelDate' => $cancelledAt];
            return match ($case) {
                '404' => Http::response([], 404),
                'rejected' => Http::response(['status_cd' => '0']),
                'wrong irn' => Http::response(['status_cd' => '1', 'data' => ['Irn' => 'different', 'CancelDate' => $cancelledAt]]),
                default => $provider === 'alankit'
                    ? Http::response(['Status' => 1, 'Data' => base64_encode(openssl_encrypt(json_encode($data), 'aes-256-ecb', $sek, OPENSSL_RAW_DATA))])
                    : Http::response(['status_cd' => '1', 'data' => $data]),
            };
        }]);
        try {
            \App\Models\EinvoiceInvoiceRel::withoutEvents(fn () => $service->cancelIrn($invoice, '2', 'Wrong details'));
            $this->assertSame('success', $case);
        } catch (\Exception $exception) {
            $this->assertNotSame('success', $case, $exception->getMessage());
            $this->assertStringContainsString(match ($case) {
                'expired' => '24-hour window',
                'active e-way bill' => 'active E-Way Bill',
                'wrong irn' => 'did not confirm cancellation',
                default => 'cancellation failed',
            }, $exception->getMessage());
        }
        $this->assertSame($case === 'success' ? 'CNL' : 'ACT', $record->fresh()->einv_status);
        if ($case === 'success') {
            $this->assertSame($cancelledAt, $record->fresh()->einv_cancel_at->toDateTimeString());
        }
        Http::assertSentCount(in_array($case, ['expired', 'active e-way bill'], true) ? 0 : 1);
    }

    public static function irnCancellationCases(): array
    {
        return [
            'Alankit confirmed' => ['alankit', 'success'],
            'PeriOne confirmed' => ['perione', 'success'],
            'gateway 404' => ['perione', '404'],
            'gateway rejection with HTTP 200' => ['perione', 'rejected'],
            'gateway confirms a different IRN' => ['perione', 'wrong irn'],
            'window expired' => ['perione', 'expired'],
            'linked batch has active e-way bill' => ['perione', 'active e-way bill'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invoiceLineAmounts')]
    public function test_einvoice_line_amounts_use_tax_exclusive_prices(array $values, bool $inclusive, array $expected): void
    {
        $item = new \App\Models\InvoiceItem(array_merge(['item_name' => 'M15', 'hsn_code' => '382450'], $values));
        $item->setRelation('orderTaxes', collect([(object) ['name' => 'IGST 18%', 'rate' => 18, 'amount' => $values['line_tax_amount']]]));
        $item->setRelation('uom', null);
        $item->setRelation('tax', null);
        $result = (new EInvoiceService)->buildItemList(collect([$item]), collect(), true, $inclusive);
        $line = $result['items'][0];
        foreach ($expected as $field => $value) {
            $this->assertEqualsWithDelta($value, $line[$field], 0.000001, $field);
        }
        $this->assertEqualsWithDelta($line['AssAmt'], $line['TotAmt'] - $line['Discount'], 0.001);
        $this->assertEqualsWithDelta($line['TotAmt'], round($line['Qty'] * $line['UnitPrice'], 2), 0.001);
        $this->assertEqualsWithDelta($values['line_total'], $line['TotItemVal'], 0.001);
    }

    public static function invoiceLineAmounts(): array
    {
        return [
            'exclusive price' => [
                ['quantity' => 5, 'price_unit' => 1200, 'discount_amount' => 0, 'subtotal' => 6000, 'line_tax_amount' => 1080, 'line_total' => 7080], false,
                ['UnitPrice' => 1200, 'TotAmt' => 6000, 'Discount' => 0, 'AssAmt' => 6000],
            ],
            'invoice 71 inclusive stored values with old flag' => [
                ['quantity' => 5, 'price_unit' => 1200, 'discount_amount' => 0, 'subtotal' => 5084.75, 'line_tax_amount' => 915.26, 'line_total' => 6000], false,
                ['UnitPrice' => 1016.95, 'TotAmt' => 5084.75, 'Discount' => 0, 'AssAmt' => 5084.75],
            ],
            'inclusive price and discount' => [
                ['quantity' => 1, 'price_unit' => 118, 'discount_amount' => 11.8, 'subtotal' => 90, 'line_tax_amount' => 16.2, 'line_total' => 106.2], true,
                ['UnitPrice' => 100, 'TotAmt' => 100, 'Discount' => 10, 'AssAmt' => 90],
            ],
            'fully discounted zero subtotal' => [
                ['quantity' => 1, 'price_unit' => 100, 'discount_amount' => 100, 'subtotal' => 0, 'line_tax_amount' => 0, 'line_total' => 0], false,
                ['UnitPrice' => 100, 'TotAmt' => 100, 'Discount' => 100, 'AssAmt' => 0],
            ],
        ];
    }

    public function test_inconsistent_taxable_values_are_rejected_before_submission(): void
    {
        $item = new \App\Models\InvoiceItem(['item_name' => 'M15', 'quantity' => 5, 'price_unit' => 1200, 'discount_amount' => 0, 'subtotal' => 4000, 'line_tax_amount' => 720, 'line_total' => 4720]);
        $item->setRelation('orderTaxes', collect([(object) ['name' => 'IGST 18%', 'rate' => 18, 'amount' => 720]]));
        $item->setRelation('tax', null);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Taxable subtotal does not match');
        (new EInvoiceService)->buildItemList(collect([$item]), collect(), true);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('alankitEnvironmentSettings')]
    public function test_alankit_environment_can_be_selected_explicitly(string $environment, ?bool $production): void
    {
        config(['services.alankit.environment' => $environment]);
        if ($production === null) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('ALANKIT_ENV must be auto, sandbox or production.');
        }

        $this->assertSame($production, app(\App\Services\AlankitAuthService::class)->isProduction());
    }

    public static function alankitEnvironmentSettings(): array
    {
        return [
            'real production' => ['production', true],
            'sandbox' => ['sandbox', false],
            'invalid environment' => ['prodution', null],
        ];
    }

    public function test_feynman_uses_sandbox_in_auto_mode(): void
    {
        config(['services.alankit.environment' => 'auto']);
        $this->app->instance('request', \Illuminate\Http\Request::create('https://feynman.modomines.com'));
        $this->assertFalse(app(\App\Services\AlankitAuthService::class)->isProduction());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('alankitGenerationCases')]
    public function test_alankit_generation_encrypts_requests_and_validates_decrypted_results(bool $production, string $responseType, ?string $expectedError): void
    {
        config(['services.alankit.subscription_key' => 'test-subscription']);
        $appKey = str_repeat('a', 32);
        $sek = str_repeat('s', 32);
        $auth = new EinvoiceAuth([
            'user_name' => 'test-user', 'auth_token' => 'alankit-token',
            'app_key' => base64_encode($appKey),
            'sek_key' => base64_encode(openssl_encrypt($sek, 'aes-256-ecb', $appKey, OPENSSL_RAW_DATA)),
        ]);
        $plant = new \App\Models\Plant(['gstin' => 'test-gstin']);
        $plant->setRelation('entity', new \App\Models\Entity(['api_key' => 'other-provider-key']));
        $authService = \Mockery::mock(\App\Services\AlankitAuthService::class);
        $authService->shouldReceive('isProduction')->with($plant)->andReturn($production);
        $this->app->instance(\App\Services\AlankitAuthService::class, $authService);
        $payload = ['Version' => '1.1', 'DocDtls' => ['No' => 'TEST-1']];
        $result = [
            'Irn' => 'alankit-issued-irn', 'AckNo' => 112610278934552,
            'AckDt' => '2026-11-01 15:12:00', 'SignedQRCode' => 'gateway-signed-qr',
            'SignedInvoice' => 'gateway-signed-invoice', 'Status' => 'ACT',
        ];
        $expectedUrl = $production
            ? 'https://www.alankitgst.com/eInvoiceGateway/eicore/v1.03/Invoice'
            : 'https://developers.eraahi.com/eInvoiceGateway/eicore/v1.03/Invoice';
        Http::preventStrayRequests();
        Http::fake([$expectedUrl => function ($request) use ($sek, $payload, $result, $responseType) {
            $this->assertSame('POST', $request->method());
            foreach (['user_name' => 'test-user', 'Ocp-Apim-Subscription-Key' => 'test-subscription', 'gstin' => 'test-gstin', 'AuthToken' => 'alankit-token'] as $name => $value) {
                $this->assertTrue($request->hasHeader($name, $value));
            }
            $plaintext = openssl_decrypt(base64_decode($request['Data']), 'aes-256-ecb', $sek, OPENSSL_RAW_DATA);
            $this->assertSame($payload, json_decode($plaintext, true));
            if ($responseType === 'rejected') {
                return Http::response(['Status' => 0, 'ErrorDetails' => [['ErrorMessage' => 'Duplicate invoice']]]);
            }
            if ($responseType === 'corrupt') {
                return Http::response(['Status' => 1, 'Data' => '%%%']);
            }
            if ($responseType === 'incomplete') {
                unset($result['SignedQRCode']);
            }
            $responseJson = $responseType === 'invalid_json' ? 'not json' : json_encode($result);

            return Http::response(['Status' => 1, 'Data' => base64_encode(openssl_encrypt($responseJson, 'aes-256-ecb', $sek, OPENSSL_RAW_DATA))]);
        }]);
        if ($expectedError !== null) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage($expectedError);
        }
        $actual = app(\App\Services\AlankitInvoiceService::class)->generate($plant, $auth, $payload);
        $this->assertSame($result, $actual);
        Http::assertSentCount(1);
    }

    public static function alankitGenerationCases(): array
    {
        return [
            'sandbox success' => [false, 'success', null],
            'production success' => [true, 'success', null],
            'gateway rejection' => [false, 'rejected', 'Duplicate invoice'],
            'corrupt encrypted response' => [false, 'corrupt', 'missing encrypted invoice data'],
            'invalid JSON response' => [false, 'invalid_json', 'Syntax error'],
            'missing signed QR' => [false, 'incomplete', 'Alankit E-Invoice Gateway did not return SignedQRCode'],
        ];
    }

    public function test_alankit_generation_is_selected_and_persists_gateway_values(): void
    {
        $plant = new \App\Models\Plant(['gstin' => 'test-gstin']);
        $plant->id = 1;
        $invoice = new \App\Models\Invoice;
        $invoice->setRelation('plant', $plant);
        $auth = new EinvoiceAuth(['auth_token' => 'alankit-token']);
        $payload = ['Version' => '1.1'];
        $gatewayData = [
            'Irn' => 'alankit-issued-irn', 'AckNo' => 112610278934552,
            'AckDt' => '2026-11-01 15:12:00', 'SignedQRCode' => 'gateway-signed-qr',
            'SignedInvoice' => 'gateway-signed-invoice',
        ];
        $gateway = \Mockery::mock(\App\Services\AlankitInvoiceService::class);
        $gateway->shouldReceive('generate')->once()->with($plant, $auth, $payload)->andReturn($gatewayData);
        $this->app->instance(\App\Services\AlankitInvoiceService::class, $gateway);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('validateForIrn')->once()->with($invoice, []);
        $service->shouldReceive('authenticate')->once()->with($plant, 1, 'alankit')->andReturn($auth);
        $service->shouldReceive('buildIrnPayload')->once()->with($invoice, [])->andReturn($payload);
        $service->shouldNotReceive('getGatewayConfig');
        $service->shouldNotReceive('sendGenerateRequest');
        $service->shouldReceive('persistIrnRecord')->once()->withArgs(function ($record, $data, $recordPlant, $userId) use ($invoice, $plant) {
            $this->assertSame('alankit-issued-irn', $data['irn']);
            $this->assertSame(112610278934552, $data['ack_no']);
            $this->assertSame('2026-11-01 15:12:00', $data['ack_date']->toDateTimeString());
            $this->assertSame('gateway-signed-qr', $data['signed_qrcode']);
            $this->assertSame('gateway-signed-invoice', $data['signed_invoice']);

            return $record === $invoice && $recordPlant === $plant && $userId === 1;
        });

        $result = $service->generate($invoice);
        $this->assertTrue($result['success']);
        $this->assertSame('alankit-issued-irn', $result['irn']);
    }

    public function test_alankit_provider_selection_uses_the_alankit_login_service(): void
    {
        $plant = new \App\Models\Plant;
        $plant->id = 1;
        $expected = new EinvoiceAuth(['auth_token' => 'alankit-token', 'app_key' => 'alankit-app-key']);
        $alankit = \Mockery::mock(\App\Services\AlankitAuthService::class);
        $alankit->shouldReceive('authenticate')->once()->with($plant, 7)->andReturn($expected);
        $this->app->instance(\App\Services\AlankitAuthService::class, $alankit);

        $this->assertSame($expected, (new EInvoiceService)->authenticate($plant, 7, 'alankit'));
    }

    public function test_authentication_defaults_to_alankit(): void
    {
        $plant = new \App\Models\Plant;
        $plant->id = 1;
        $expected = new EinvoiceAuth(['auth_token' => 'alankit-token']);
        $alankit = \Mockery::mock(\App\Services\AlankitAuthService::class);
        $alankit->shouldReceive('authenticate')->once()->with($plant, 7)->andReturn($expected);
        $this->app->instance(\App\Services\AlankitAuthService::class, $alankit);

        $this->assertSame('alankit', config('services.einvoice.provider'));
        $this->assertSame($expected, (new EInvoiceService)->authenticate($plant, 7));
    }

    public function test_perione_can_still_be_selected_explicitly(): void
    {
        $plant = new \App\Models\Plant;
        $plant->id = 1;
        $expected = new EinvoiceAuth(['auth_token' => 'perione-token']);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('getCachedAuthToken')->once()->with(1, 7)->andReturn($expected);

        $this->assertSame($expected, $service->authenticate($plant, 7, 'perione'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('alankitEnvironments')]
    public function test_alankit_authentication_encrypts_credentials_and_uses_provider_headers(bool $production): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $opensslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($opensslConfig)) {
            $options['config'] = $opensslConfig;
        }
        $key = openssl_pkey_new($options);
        $path = tempnam(sys_get_temp_dir(), 'alankit-test-');
        file_put_contents($path, openssl_pkey_get_details($key)['key']);
        $environment = $production ? 'production' : 'sandbox';
        config(["services.alankit.{$environment}_public_key_path" => $path]);
        $expectedUrl = $production
            ? 'https://www.alankitgst.com/eInvoiceGateway/eivital/v1.04/auth'
            : 'https://developers.eraahi.com/eInvoiceGateway/eivital/v1.04/auth';
        Http::preventStrayRequests();
        $sentAppKey = null;
        Http::fake([$expectedUrl => function ($request) use ($key, &$sentAppKey) {
            $this->assertSame('POST', $request->method());
            $this->assertTrue($request->hasHeader('Ocp-Apim-Subscription-Key', 'test-subscription'));
            $this->assertTrue($request->hasHeader('gstin', 'test-gstin'));
            $this->assertFalse($request->hasHeader('auth-token'));
            $this->assertTrue(openssl_private_decrypt(base64_decode($request['Data']), $decrypted, $key, OPENSSL_PKCS1_PADDING));
            $credentials = json_decode(base64_decode($decrypted), true);
            $this->assertSame('test-user', $credentials['UserName']);
            $this->assertSame('test-password', $credentials['Password']);
            $this->assertTrue($credentials['ForceRefreshAccessToken']);
            $sentAppKey = $credentials['AppKey'];
            $this->assertSame(32, strlen(base64_decode($sentAppKey)));

            return Http::response(['Status' => 1, 'Data' => [
                'UserName' => 'test-user', 'AuthToken' => 'alankit-token',
                'Sek' => 'encrypted-sek', 'TokenExpiry' => '2026-11-01 20:00:00',
            ]]);
        }]);

        try {
            $result = app(\App\Services\AlankitAuthService::class)->requestAuthFromGateway(
                'test-user', 'test-password', 'test-gstin', 'test-subscription', $production
            );
            $this->assertSame($sentAppKey, $result['app_key']);
            $this->assertSame('alankit-token', $result['token']);
            $this->assertSame('encrypted-sek', $result['sek']);
            $this->assertSame('2026-11-01 20:00:00', $result['token_expiry']->toDateTimeString());
            Http::assertSentCount(1);
        } finally {
            unlink($path);
        }
    }

    public static function alankitEnvironments(): array
    {
        return ['sandbox' => [false], 'production' => [true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('alankitErrorFormats')]
    public function test_alankit_rejects_unsuccessful_authentication(string $format): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $opensslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($opensslConfig)) {
            $options['config'] = $opensslConfig;
        }
        $key = openssl_pkey_new($options);
        $path = tempnam(sys_get_temp_dir(), 'alankit-test-');
        file_put_contents($path, openssl_pkey_get_details($key)['key']);
        config(['services.alankit.sandbox_public_key_path' => $path]);
        $error = ['ErrorCode' => '1018', 'ErrorMessage' => 'Client Id is not mapped to this user'];
        $errors = match ($format) {
            'base64' => base64_encode(json_encode([$error])),
            'json' => json_encode([$error]),
            'object' => $error,
            default => [$error],
        };
        Http::preventStrayRequests();
        Http::fake(['https://developers.eraahi.com/*' => Http::response([
            'Status' => 0, 'ErrorDetails' => $errors,
        ])]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client Id is not mapped to this user (Error Code: 1018)');
        try {
            app(\App\Services\AlankitAuthService::class)->requestAuthFromGateway('test-user', 'test-password', 'test-gstin', 'test-subscription');
        } finally {
            unlink($path);
        }
    }

    public static function alankitErrorFormats(): array
    {
        return ['array' => ['array'], 'documented base64' => ['base64'], 'json string' => ['json'], 'single object' => ['object']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phoneNumbers')]
    public function test_seller_and_buyer_phone_fields_follow_gateway_length_rules(mixed $phone, ?string $expected): void
    {
        $service = new EInvoiceService;
        $plant = \Mockery::mock(\App\Models\Plant::class)->makePartial();
        $plant->mobile_number = $phone;
        $plant->shouldReceive('addresses->first')->andReturn(null);
        $partner = \Mockery::mock(\App\Models\Patron::class)->makePartial();
        $partner->phone = $phone;
        $partner->email = 'test@example.com';
        $partner->shouldReceive('addresses->first')->andReturn(null);
        $partner->shouldReceive('contacts->first')->andReturn(null);

        $details = [
            $service->buildSellerDetails($plant, null, '29AARFB4347G000', '29'),
            $service->buildBuyerDetails($partner, '29AWGPV7107B1Z1', '29'),
        ];
        foreach ($details as $data) {
            if ($expected === null) {
                $this->assertArrayNotHasKey('Ph', $data);
            } else {
                $this->assertSame($expected, $data['Ph']);
                $this->assertSame($expected, json_decode(json_encode($data), true)['Ph']);
            }
        }
    }

    public static function phoneNumbers(): array
    {
        return [
            'missing' => [null, null],
            'blank' => ['', null],
            'spaces' => ['   ', null],
            'short' => ['12345', null],
            'minimum' => ['123456', '123456'],
            'formatted mobile' => ['98765-43210', '9876543210'],
            'country code' => ['+91 98765 43210', '919876543210'],
            'numeric source' => [9876543210, '9876543210'],
            'leading zero' => ['044-23456789', '04423456789'],
            'too long' => ['0091 98765 43210', null],
            'multiple numbers' => ['9876543210 / 9123456789', null],
        ];
    }

    public function test_sandbox_posts_invoice_to_staging_and_returns_gateway_irn(): void
    {
        Http::preventStrayRequests();
        $gatewayData = [
            'Irn' => 'staging-issued-irn', 'AckNo' => 123456789012345,
            'AckDt' => '2026-11-01 15:00:00', 'SignedQRCode' => 'gateway-signed-qr',
            'SignedInvoice' => 'gateway-signed-invoice', 'Status' => 'ACT',
        ];
        Http::fake(['https://staging.perione.in/*' => Http::response(['status_cd' => '1', 'data' => $gatewayData])]);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('isProduction')->andReturn(false);
        $service->getGatewayConfig();
        $auth = new EinvoiceAuth(['auth_token' => 'staging-token', 'sek_key' => 'staging-sek']);
        $headers = $service->buildGatewayHeaders($auth, 'test-user', 'test-password', '29AARFB4347G000');
        $payload = [
            'SellerDtls' => ['Gstin' => '29AARFB4347G000'],
            'BuyerDtls' => ['Gstin' => 'URP'],
            'DocDtls' => ['No' => 'SANDBOX-1', 'Dt' => '01/11/2026'],
            'ValDtls' => ['TotInvVal' => 1180],
            'ItemList' => [['SlNo' => '1']],
        ];

        $this->assertSame($gatewayData, $service->sendGenerateRequest($headers, $payload));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://staging.perione.in/einvoice/type/GENERATE/version/V1_03?email=sayee%40onemodo.com'
            && $request->hasHeader('auth-token', 'staging-token')
            && !$request->hasHeader('auth_token')
            && $request->hasHeader('client_id', $service->clientId)
            && $request->hasHeader('client_secret', $service->clientSecret)
            && $request->hasHeader('username', 'test-user')
            && $request->hasHeader('gstin', '29AARFB4347G000')
            && $request->data() === $payload);
    }

    public function test_production_returns_gateway_irn_data(): void
    {
        Http::preventStrayRequests();
        $gatewayData = [
            'Irn' => 'gateway-irn', 'AckNo' => 123456789012345,
            'AckDt' => '2026-11-01 15:00:00', 'SignedQRCode' => 'gateway-signed-qr',
            'SignedInvoice' => 'gateway-signed-invoice',
        ];
        Http::fake(['https://gateway.example/*' => Http::response(['data' => $gatewayData])]);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('isProduction')->andReturn(true);
        $service->baseUrl = 'https://gateway.example';
        $service->email = 'test@example.com';

        $this->assertSame($gatewayData, $service->sendGenerateRequest(['auth-token' => 'test-token'], ['Version' => '1.1']));
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://gateway.example/einvoice/type/GENERATE/version/V1_03?email=test%40example.com'
            && $request->hasHeader('auth-token', 'test-token')
            && $request['Version'] === '1.1');
    }

    public function test_production_does_not_generate_sample_irn_on_gateway_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://gateway.example/*' => Http::response(['message' => 'Endpoint not found'], 404)]);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('isProduction')->andReturn(true);
        $service->baseUrl = 'https://gateway.example';
        $service->email = 'test@example.com';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PeriOne E-Invoice Generation Failed: Endpoint not found');
        $service->sendGenerateRequest([], []);
    }

    public function test_html_gateway_errors_are_returned_as_plain_text(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://staging.perione.in/*' => Http::response(
            '<!DOCTYPE html><html><head><title>Error</title></head><body><pre>Cannot POST /einvoice/generate</pre></body></html>',
            404,
            ['Content-Type' => 'text/html']
        )]);
        $service = new EInvoiceService;
        $service->baseUrl = 'https://staging.perione.in';
        $service->email = 'test@example.com';

        try {
            $service->sendGenerateRequest([], []);
            $this->fail('Expected a gateway exception.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Cannot POST /einvoice/generate', $exception->getMessage());
            $this->assertStringNotContainsString('<', $exception->getMessage());
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sandboxFailures')]
    public function test_sandbox_rejects_gateway_errors_without_sample_fallback(array $body, int $status, string $message): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://staging.perione.in/*' => Http::response($body, $status)]);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('isProduction')->andReturn(false);
        $service->getGatewayConfig();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);
        $service->sendGenerateRequest([], []);
    }

    public static function sandboxFailures(): array
    {
        return [
            'not found' => [['message' => 'Endpoint not found'], 404, 'Endpoint not found'],
            'http failure' => [['message' => 'Invalid invoice'], 400, 'Invalid invoice'],
            'gateway rejection' => [['status_cd' => '0', 'status_desc' => 'Duplicate document'], 200, 'Duplicate document'],
            'missing irn' => [['data' => ['AckNo' => 123]], 200, 'Gateway returned an invalid response'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteIrnResponses')]
    public function test_incomplete_api_responses_are_rejected_in_both_environments(bool $production, string $missingField): void
    {
        Http::preventStrayRequests();
        $data = [
            'Irn' => 'gateway-irn', 'AckNo' => 123456789012345,
            'AckDt' => '2026-11-01 15:00:00', 'SignedQRCode' => 'gateway-signed-qr',
            'SignedInvoice' => 'gateway-signed-invoice',
        ];
        unset($data[$missingField]);
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('isProduction')->andReturn($production);
        $service->getGatewayConfig();
        Http::fake([$service->baseUrl . '/*' => Http::response(['data' => $data])]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("PeriOne E-Invoice Gateway did not return {$missingField}.");
        $service->sendGenerateRequest([], []);
    }

    public static function incompleteIrnResponses(): array
    {
        $cases = [];
        foreach ([false, true] as $production) {
            foreach (['AckNo', 'AckDt', 'SignedQRCode', 'SignedInvoice'] as $field) {
                $cases[($production ? 'production' : 'sandbox') . ' missing ' . $field] = [$production, $field];
            }
        }

        return $cases;
    }

    public function test_sandbox_connection_failures_propagate(): void
    {
        $request = \Mockery::mock(\Illuminate\Http\Client\PendingRequest::class);
        Http::shouldReceive('withHeaders')->once()->with([])->andReturn($request);
        $request->shouldReceive('timeout')->once()->with(30)->andReturnSelf();
        $request->shouldReceive('post')->once()
            ->with('https://staging.perione.in/einvoice/type/GENERATE/version/V1_03?email=sayee%40onemodo.com', [])
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('Connection timed out'));
        $service = \Mockery::mock(EInvoiceService::class)->makePartial();
        $service->shouldReceive('isProduction')->andReturn(false);
        $service->getGatewayConfig();

        $this->expectException(\Illuminate\Http\Client\ConnectionException::class);
        $service->sendGenerateRequest([], []);
    }

    public function test_authentication_without_app_key_can_be_saved_and_cached(): void
    {
        config(['database.default' => 'einvoice_test', 'database.connections.einvoice_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);

        Schema::create('mm_einvoice_auth', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('plant_id');
            $table->integer('user_id');
            $table->string('user_name', 100);
            $table->string('app_key', 255);
            $table->string('auth_token', 255);
            $table->string('sek_key', 255);
            $table->dateTime('token_generated_at');
            $table->dateTime('token_expiry_at');
            $table->integer('created_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->softDeletes();
        });

        $migration = require database_path('migrations/2026_11_01_140809_make_einvoice_auth_app_key_nullable.php');
        $migration->up();

        Http::preventStrayRequests();
        Http::fake(['https://gateway.example/*' => Http::response(['data' => [
            'AuthToken' => 'test-token', 'Sek' => 'test-sek',
            'TokenExpiry' => Carbon::now()->addHour()->toDateTimeString(),
        ]])]);

        $service = new EInvoiceService;
        $service->baseUrl = 'https://gateway.example';
        $service->email = 'test@example.com';
        $data = $service->requestAuthFromGateway('test-user', 'test-password', 'test-gstin');
        $record = $service->storeAuthRecord(1, 1, 'test-user', $data['token'], $data['sek'], $data['token_expiry']);

        $this->assertNull($record->fresh()->app_key);
        $this->assertSame('test-token', $service->getCachedAuthToken(1, 1)->auth_token);
        $this->assertSame(1, $record->created_by);

        // Exercise refreshes without invoking the unrelated audit log observer.
        EinvoiceAuth::withoutEvents(function () use ($service, $record) {
            $withoutKey = $service->storeAuthRecord(1, 1, 'test-user', 'latest-token', 'latest-sek', Carbon::now()->addHour());
            $this->assertSame($record->id, $withoutKey->id);
            $this->assertNull($withoutKey->fresh()->app_key);
            $this->assertSame('latest-token', $withoutKey->fresh()->auth_token);
        });
        $this->assertSame(1, DB::table('mm_einvoice_auth')->count());

        EinvoiceAuth::withoutEvents(function () use ($service) {
            $alankit = $service->storeAuthRecord(1, 1, 'test-user', 'alankit-token', 'encrypted-sek', Carbon::now()->addHour(), 'alankit-app-key');
            $this->assertSame('alankit-app-key', $alankit->fresh()->app_key);
            $this->assertNull($service->getCachedAuthToken(1, 1));
            $service->storeAuthRecord(1, 1, 'test-user', 'perione-token', 'perione-sek', Carbon::now()->addHour());
            $this->assertSame('perione-token', $service->getCachedAuthToken(1, 1)->auth_token);
        });

        $migration->down();
        $this->assertSame('', DB::table('mm_einvoice_auth')->value('app_key'));
    }
}
