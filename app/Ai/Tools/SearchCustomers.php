<?php

namespace App\Ai\Tools;

use App\Models\Patron;
use Laravel\Ai\Contracts\Tool;
use Stringable;

/**
 * Tool: Search Customers
 *
 * Allows the internal assistant agent to search for customers (patrons)
 * by name, code, phone, or email.
 */
class SearchCustomers implements Tool
{
    public function name(): Stringable|string
    {
        return 'search_customers';
    }

    public function description(): Stringable|string
    {
        return 'Search for customers by name, customer code, phone number, or email address. Returns a list of matching customers with their basic details.';
    }

    public function parameters(): array
    {
        return [
            'query' => [
                'type'        => 'string',
                'description' => 'Search term — customer name, code, phone, or email',
                'required'    => true,
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Maximum number of results to return (default: 10)',
                'required'    => false,
            ],
        ];
    }

    public function handle(string $query, int $limit = 10): string
    {
        $plantId  = session('active_plant_id');
        $entityId = null;

        if ($plantId) {
            $plant    = \App\Models\Plant::find($plantId);
            $entityId = $plant?->entity_id;
        }

        $dbQuery = Patron::query()
            ->select(['id', 'name', 'code', 'patron_type'])
            ->with(['contacts:id,patron_id,phone,mobile,email'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('code', 'LIKE', "%{$query}%");
            })
            ->take(min($limit, 20));

        if ($entityId) {
            $dbQuery->where('entity_id', $entityId);
        }

        $customers = $dbQuery->get();

        if ($customers->isEmpty()) {
            return "No customers found matching '{$query}'.";
        }

        $result = "Found {$customers->count()} customer(s):\n\n";

        foreach ($customers as $customer) {
            $contact = $customer->contacts?->first();
            $result .= "• **{$customer->name}**";
            if ($customer->code) {
                $result .= " (Code: {$customer->code})";
            }
            if ($contact) {
                if ($contact->phone || $contact->mobile) {
                    $result .= "\n  Phone: " . ($contact->mobile ?: $contact->phone);
                }
                if ($contact->email) {
                    $result .= "\n  Email: {$contact->email}";
                }
            }
            $result .= "\n  Type: {$customer->patron_type}\n\n";
        }

        return $result;
    }
}
