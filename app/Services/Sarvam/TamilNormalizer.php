<?php

namespace App\Services\Sarvam;

use App\Services\Sarvam\Exceptions\SarvamApiException;
use Illuminate\Support\Facades\Log;

class TamilNormalizer
{
    public function __construct(
        private readonly SarvamClient $client
    ) {}

    /**
     * Normalize user input: if Tanglish (dominant Latin characters representing Tamil),
     * transliterate it to native Tamil script using Sarvam AI Transliteration API.
     *
     * @param string $text
     * @return string
     */
    public function normalize(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        if ($this->isTanglishDominant($trimmed)) {
            try {
                $result = $this->client->transliterate($trimmed, 'en-IN', 'ta-IN');
                return $result->transliteratedText;
            } catch (SarvamApiException $e) {
                Log::error('TamilNormalizer: Transliteration failed, falling back to original text', [
                    'text' => $trimmed,
                    'error' => $e->getMessage(),
                    'response' => $e->responseBody,
                ]);
            } catch (\Throwable $e) {
                Log::error('TamilNormalizer: Unexpected exception during transliteration', [
                    'text' => $trimmed,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $text;
    }

    /**
     * Check if the text is Tanglish dominant (more English/Latin alphabet letters than Tamil characters).
     *
     * @param string $text
     * @return bool
     */
    private function isTanglishDominant(string $text): bool
    {
        // Count Latin letters (a-z, A-Z)
        $latinCount = preg_match_all('/[a-zA-Z]/u', $text);

        // Count Tamil Unicode Block characters (U+0B80 to U+0BFF)
        $tamilCount = preg_match_all('/[\x{0B80}-\x{0BFF}]/u', $text);

        if ($latinCount === 0 && $tamilCount === 0) {
            return false;
        }

        // Dominance threshold: if Latin character count is greater than Tamil character count
        return $latinCount > $tamilCount;
    }
}
