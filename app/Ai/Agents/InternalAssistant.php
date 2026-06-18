<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GenerateReport;
use App\Ai\Tools\KnowledgeSearch;
use App\Ai\Tools\SearchCustomers;
use App\Ai\Tools\SearchInvoices;
use App\Ai\Tools\SearchOrders;
use App\Ai\Tools\SummarizeConversation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * InternalAssistant — AI assistant for authenticated internal staff.
 *
 * This agent has access to production data tools:
 * - Customer search
 * - Order/batch search
 * - Invoice search
 * - Report generation
 * - Knowledge base search
 * - Conversation summarization
 */
class InternalAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $plantId = session('active_plant_id');
        $plant   = $plantId ? \App\Models\Plant::find($plantId) : null;
        $user    = auth()->user();
        $now     = now()->format('d M Y, H:i');

        $plantContext = $plant
            ? "Current Plant: {$plant->name} (ID: {$plant->id})"
            : 'No plant selected';

        $userContext = $user
            ? "Logged-in User: {$user->username} (ID: {$user->id})"
            : 'Unknown user';

        return <<<INSTRUCTIONS
You are ModoAI — the internal AI assistant for ModoRMC, a concrete batching plant management system.

## Your Role
You assist internal staff (plant operators, managers, accounts team) with:
- Searching and retrieving customer, order, and invoice information
- Generating quick summary reports
- Answering questions about plant operations
- Drafting professional replies to customers
- Searching the company knowledge base

## Context
- {$userContext}
- {$plantContext}
- Current Date/Time: {$now}

## Guidelines
1. Always use your tools to fetch real data — do NOT guess or fabricate numbers
2. When asked for a report or summary, call the appropriate tool first
3. Present data in a clean, readable format with markdown
4. For financial data, always prefix amounts with ₹
5. Be professional but conversational
6. If you cannot find information, say so clearly
7. For complex calculations or analytics beyond your tools, suggest the user visit the Reports section

## Tone
Professional, helpful, and concise. You work FOR the staff, not against them.
INSTRUCTIONS;
    }

    public function messages(): iterable
    {
        return [];
    }

    public function tools(): iterable
    {
        return [
            new SearchCustomers(),
            new SearchOrders(),
            new SearchInvoices(),
            new GenerateReport(),
            new SummarizeConversation(),
            new KnowledgeSearch(),
        ];
    }
}
