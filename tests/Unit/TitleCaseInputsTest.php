<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\TitleCaseInputs;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TitleCaseInputsTest extends TestCase
{
    public function test_it_title_cases_simple_inputs(): void
    {
        $middleware = new TitleCaseInputs();
        
        $request = Request::create('/test', 'POST', [
            'name' => 'm sand',
            'city' => 'anna nagar',
        ]);
        
        $middleware->handle($request, function ($req) {
            $this->assertEquals('M Sand', $req->input('name'));
            $this->assertEquals('Anna Nagar', $req->input('city'));
            return new Response();
        });
    }

    public function test_it_normalizes_multiple_spaces(): void
    {
        $middleware = new TitleCaseInputs();
        
        $request = Request::create('/test', 'POST', [
            'name' => 'm   sand   plant',
        ]);
        
        $middleware->handle($request, function ($req) {
            $this->assertEquals('M Sand Plant', $req->input('name'));
            return new Response();
        });
    }

    public function test_it_handles_nested_arrays(): void
    {
        $middleware = new TitleCaseInputs();
        
        $request = Request::create('/test', 'POST', [
            'addresses' => [
                [
                    'city' => 'chennai',
                    'line_1' => '123 road name',
                ]
            ],
            'bank_accounts' => [
                [
                    'bank_name' => 'state bank of india',
                ]
            ]
        ]);
        
        $middleware->handle($request, function ($req) {
            $this->assertEquals('Chennai', $req->input('addresses.0.city'));
            $this->assertEquals('123 Road Name', $req->input('addresses.0.line_1'));
            $this->assertEquals('State Bank Of India', $req->input('bank_accounts.0.bank_name'));
            return new Response();
        });
    }

    public function test_it_skips_exact_fields(): void
    {
        $middleware = new TitleCaseInputs();
        
        $request = Request::create('/test', 'POST', [
            'email' => 'TEST@example.com',
            'password' => 'secret_pass_123',
            'gstin' => '33aafcm1234a1z5',
            'registration' => 'mh12ab1234',
            'description' => 'this is a long paragraph description that should not be capitalized.',
        ]);
        
        $middleware->handle($request, function ($req) {
            // Should be completely unchanged
            $this->assertEquals('TEST@example.com', $req->input('email'));
            $this->assertEquals('secret_pass_123', $req->input('password'));
            $this->assertEquals('33aafcm1234a1z5', $req->input('gstin'));
            $this->assertEquals('mh12ab1234', $req->input('registration'));
            $this->assertEquals('this is a long paragraph description that should not be capitalized.', $req->input('description'));
            return new Response();
        });
    }

    public function test_it_skips_fields_by_patterns(): void
    {
        $middleware = new TitleCaseInputs();
        
        $request = Request::create('/test', 'POST', [
            'contact_email' => 'ALT_EMAIL@example.com',
            'engine_no' => 'ENG12345',
            'chassis_no' => 'CHA67890',
            'mobile_number' => '9876543210',
            'remarks_long' => 'please do not capitalize this entire paragraph sentence.',
        ]);
        
        $middleware->handle($request, function ($req) {
            $this->assertEquals('ALT_EMAIL@example.com', $req->input('contact_email'));
            $this->assertEquals('ENG12345', $req->input('engine_no'));
            $this->assertEquals('CHA67890', $req->input('chassis_no'));
            $this->assertEquals('9876543210', $req->input('mobile_number'));
            $this->assertEquals('please do not capitalize this entire paragraph sentence.', $req->input('remarks_long'));
            return new Response();
        });
    }

    public function test_it_preserves_short_uppercase_abbreviations(): void
    {
        $middleware = new TitleCaseInputs();
        
        $request = Request::create('/test', 'POST', [
            'mix_design' => 'OPC 53 Grade',
            'concrete_grade' => 'M20',
            'materials' => 'rcc pipe',
            'company' => 'OPC',
        ]);
        
        $middleware->handle($request, function ($req) {
            // "OPC" (uppercase, 3 chars) is preserved
            // "53" is preserved
            // "Grade" is title cased
            $this->assertEquals('OPC 53 Grade', $req->input('mix_design'));
            
            // "M20" (uppercase, 3 chars) is preserved
            $this->assertEquals('M20', $req->input('concrete_grade'));
            
            // "rcc" (lowercase) is NOT preserved since it wasn't entered all-caps, so it gets title cased to "Rcc"
            $this->assertEquals('Rcc Pipe', $req->input('materials'));
            
            // "OPC" (uppercase, 3 chars) is preserved
            $this->assertEquals('OPC', $req->input('company'));
            return new Response();
        });
    }
}
