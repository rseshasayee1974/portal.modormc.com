<?php

/*
Author: ragul-onemodo
Created: 2026-11-01 10:24:27 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Services\PrintDataFormatter;
use Illuminate\Foundation\Testing\TestCase;

class InvoiceQrCodeTest extends TestCase
{
    public function test_absolute_payment_qr_urls_are_preserved(): void
    {
        foreach (['https://modormc.com/storage/plants/example/qr.png', 'https://modormc.com/plants/example/qr.png', 'https://payments.example/qr.png'] as $url) {
            $this->assertSame($url, PrintDataFormatter::formatQrCodeUrl(null, null, $url));
        }
        $dataUri = 'data:image/png;base64,example';
        $this->assertSame($dataUri, PrintDataFormatter::formatQrCodeUrl(null, null, $dataUri));
    }

    public function test_relative_payment_qr_paths_get_one_storage_prefix(): void
    {
        foreach (['plants/example/qr.png', '/storage/plants/example/qr.png', 'public/storage/plants/example/qr.png'] as $path) {
            $this->assertSame(asset('storage/plants/example/qr.png'), PrintDataFormatter::formatQrCodeUrl(null, null, $path));
        }
        $this->assertSame(asset('storage/plants/storage/qr.png'), PrintDataFormatter::formatQrCodeUrl(null, null, 'plants/storage/qr.png'));
    }

    public function test_real_einvoice_qr_has_priority_and_missing_qr_stays_empty(): void
    {
        $signedQr = 'https://einvoice.example/signed-qr.png';
        $this->assertSame($signedQr, PrintDataFormatter::formatQrCodeUrl($signedQr, null, 'plants/payment.png'));
        $this->assertSame('', PrintDataFormatter::formatQrCodeUrl(null, null));
        $this->assertSame('', PrintDataFormatter::formatQrCodeUrl('', ''));
        $this->assertSame('', PrintDataFormatter::formatQrCodeUrl(null, 'gateway-irn'));
        $this->assertSame('', PrintDataFormatter::formatQrCodeUrl(null, 'gateway-irn', 'plants/payment.png'));
        $url = PrintDataFormatter::formatQrCodeUrl('signed invoice payload', null);
        $this->assertStringContainsString('data=signed+invoice+payload', $url);
    }
}
