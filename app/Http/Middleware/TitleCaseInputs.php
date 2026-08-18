<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TitleCaseInputs Middleware
 *
 * Automatically converts qualifying text string inputs to Title Case
 * before they reach validation or controllers.
 *
 * Example: "m sand" → "M Sand", "anna nagar" → "Anna Nagar"
 *
 * Fields listed in $skipFields or matching $skipPatterns are excluded
 * (e.g. email, password, codes, IPs, tokens, slugs, URLs).
 */
class TitleCaseInputs
{
    /**
     * Exact field names (dot-notation for nested) that should NEVER be title-cased.
     */
    protected array $skipFields = [
        // Authentication
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'api_key',
        'api_token',
        'remember_token',
        '_token',

        // Identifiers & codes that must preserve case or formatting
        'email',
        'email_address',
        'username',
        'slug',
        'url',
        'redirect',
        'redirect_url',
        'link',
        'gstin',
        'pan_no',
        'pan',
        'cin',
        'uin',
        'ifsc_code',
        'swift_code',
        'account_number',
        'code',
        'prefix',
        'order_no',
        'invoice_no',
        'reference_no',
        'challan_no',
        'batch_no',
        'eway_bill_no',
        'irn',
        'ack_no',
        'registration',
        'registration_no',

        // Technical / API fields
        'scheduler_api_url',
        'scheduler_oauth_url',
        'scheduler_api_token',
        'scheduler_client_id',
        'scheduler_client_secret',
        'einvoice_client_id',
        'einvoice_secret',
        'ewaybill_client_id',
        'ewaybill_secret',
        'plc_ip',
        'latitude',
        'longitude',
        'time_zone',

        // Numeric strings & formatted values
        'mobile',
        'contact_mobile',
        'contact_alt_mobile',
        'alt_mobile',
        'landline',
        'mobile_number',
        'phone',
        'zipcode',
        'address_zipcode',
        'pincode',
        'gst_rate',

        // Timestamps & dates
        'scheduled_start',
        'scheduled_end',
        'start_time',
        'end_time',
        'shift_start_time',
        'shift_end_time',
        'date',
        'due_date',
        'payment_date',
        'invoice_date',
        'order_date',

        // JSON / binary / file fields
        'logo',
        'logo_file',
        'photo',
        'signature',
        'attachment',
        'document',

        // Misc flags & selectors
        'status',
        'type',
        'guard_name',
        'concrete_pump',
        'tax_type',
        'tax_number',

        // Long text / descriptions (to avoid Title Case on paragraphs)
        'description',
        'notes',
        'remarks',
        'terms_condition',
        'message',
    ];

    /**
     * Regex patterns — any field whose key CONTAINS one of these substrings is skipped.
     */
    protected array $skipPatterns = [
        'password',
        'email',
        'token',
        'secret',
        'url',
        'api',
        'oauth',
        'client_id',
        'client_secret',
        'ifsc',
        'swift',
        'gstin',
        'pan_',
        '_no',
        'number',
        'mobile',
        'phone',
        'zipcode',
        'pincode',
        'lat',
        'lon',
        'ip',
        'plc',
        'irn',
        'ack',
        'eway',
        'einvoice',

        // Long text substrings
        'desc',
        'note',
        'remark',
        'terms',
        'msg',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $input = $request->all();
            $input = $this->applyTitleCase($input);
            $request->replace($input);
        }

        return $next($request);
    }

    /**
     * Recursively apply title-case to string values in the input array.
     *
     * @param array  $data   The input data array
     * @param string $prefix Dot-notation prefix for nested keys (e.g. "address.")
     */
    protected function applyTitleCase(array $data, string $prefix = ''): array
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix . $key;

            if (is_array($value)) {
                // For numeric-indexed arrays (e.g. bank_accounts[0], addresses[0])
                // re-enter without adding index to the key prefix so the leaf field
                // names still match the skip-list (e.g. "bank_accounts.bank_name").
                if (array_is_list($value)) {
                    $data[$key] = array_map(
                        fn($item) => is_array($item)
                            ? $this->applyTitleCase($item, $prefix . $key . '.')
                            : $item,
                        $value
                    );
                } else {
                    $data[$key] = $this->applyTitleCase($value, $fullKey . '.');
                }
            } elseif (is_string($value) && $value !== '' && !$this->shouldSkip($fullKey, $key)) {
                $data[$key] = $this->toTitleCase($value);
            }
        }

        return $data;
    }

    /**
     * Determine whether a field should be skipped (not title-cased).
     *
     * @param string $fullKey  Full dot-notation key (e.g. "address.city")
     * @param string $shortKey The final segment (e.g. "city")
     */
    protected function shouldSkip(string $fullKey, string $shortKey): bool
    {
        // Check exact skip list (short key)
        if (in_array($shortKey, $this->skipFields, true)) {
            return true;
        }

        // Check exact skip list (full dot-notation key)
        if (in_array($fullKey, $this->skipFields, true)) {
            return true;
        }

        // Check pattern substrings against the short key
        foreach ($this->skipPatterns as $pattern) {
            if (str_contains(strtolower($shortKey), $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a string value to Title Case using PHP's ucwords.
     * Handles multiple spaces and preserves intentional all-caps abbreviations
     * that are 3 chars or fewer (e.g. "M20", "RCC", "OPC").
     */
    protected function toTitleCase(string $value): string
    {
        // Normalize multiple spaces to single
        $value = preg_replace('/\s+/', ' ', trim($value));

        $words = explode(' ', $value);
        foreach ($words as &$word) {
            // Preserve intentional all-caps abbreviations that are 3 chars or fewer (e.g. "RCC", "OPC", "M20")
            if (strlen($word) <= 3 && strtoupper($word) === $word) {
                continue;
            }
            $word = ucwords(strtolower($word));
        }

        return implode(' ', $words);
    }
}
