<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class Base64Image implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            self::decode($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }

    /** @return array{data: string, extension: string} */
    public static function decode(mixed $value): array
    {
        $message = 'The camera snapshot is not a readable image. Please capture it again.';
        if (!is_string($value)) {
            throw new InvalidArgumentException($message);
        }

        $encoded = trim($value);
        if (str_contains($encoded, ',')) {
            [$header, $encoded] = explode(',', $encoded, 2);
            if (!preg_match('/^data:[^,]*;base64$/i', $header)) {
                throw new InvalidArgumentException($message);
            }
        }

        $cleanBase64 = str_replace(' ', '+', $encoded);
        $cleanBase64 = preg_replace('/[^A-Za-z0-9\+\/=]/', '', $cleanBase64);
        $data = base64_decode($cleanBase64, true);
        $info = $data !== false && $data !== '' ? @getimagesizefromstring($data) : false;
        $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($extensions[$info[2]])) {
            throw new InvalidArgumentException($message);
        }

        // Check the pixel data as well as the header; a JPEG signature alone
        // can survive a corrupted Base64 payload.
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            throw new InvalidArgumentException($message);
        }
        imagedestroy($image);

        return ['data' => $data, 'extension' => $extensions[$info[2]]];
    }
}
