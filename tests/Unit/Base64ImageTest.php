<?php

namespace Tests\Unit;

use App\Http\Middleware\TitleCaseInputs;
use App\Rules\Base64Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class Base64ImageTest extends TestCase
{
    private function imageBytes(string $format = 'jpeg'): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        if ($format === 'png') {
            imagepng($image);
        } else {
            imagejpeg($image);
        }
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    public function test_snapshot_survives_request_formatting_and_decodes_to_the_original_jpeg(): void
    {
        $bytes = $this->imageBytes();
        $value = 'data:image/jpeg;base64,' . base64_encode($bytes);
        $request = Request::create('/inventory/inwards', 'POST', [
            'items' => [['loaded_weight_photo' => $value]],
        ]);

        (new TitleCaseInputs)->handle($request, function ($request) use ($bytes) {
            $value = $request->input('items.0.loaded_weight_photo');
            $validator = Validator::make(['photo' => $value], ['photo' => [new Base64Image]]);
            $this->assertTrue($validator->passes());
            $decoded = Base64Image::decode($value);
            $this->assertSame($bytes, $decoded['data']);
            $this->assertSame('jpg', $decoded['extension']);
            $this->assertSame(8, getimagesizefromstring($decoded['data'])[0]);
            return new Response;
        });
    }

    public function test_extension_comes_from_image_bytes_not_the_claimed_mime_type(): void
    {
        $bytes = $this->imageBytes('png');
        $decoded = Base64Image::decode('data:image/jpeg;base64,' . base64_encode($bytes));
        $this->assertSame('png', $decoded['extension']);
        $this->assertSame($bytes, $decoded['data']);
    }

    public function test_raw_base64_payload_is_supported(): void
    {
        $bytes = $this->imageBytes();
        $this->assertSame($bytes, Base64Image::decode(base64_encode($bytes))['data']);
    }

    public function test_corrupt_or_non_image_payloads_produce_a_validation_error(): void
    {
        $jpeg = base64_encode($this->imageBytes());
        foreach ([
            'data:image/jpeg;base64,' . strtolower($jpeg),
            'data:image/jpeg;base64,' . base64_encode('<html>Camera unavailable</html>'),
            'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF" . str_repeat('invalid', 30)),
            'data:image/jpeg;base64,invalid!base64',
            'https://example.com/camera.jpg',
        ] as $value) {
            $validator = Validator::make(['photo' => $value], ['photo' => [new Base64Image]]);
            $this->assertTrue($validator->fails());
            $this->assertStringContainsString('capture it again', $validator->errors()->first('photo'));
        }
    }
}
