<?php

namespace App\Ai\Tools;

use App\Models\AgentChatHistory;
use Laravel\Ai\Contracts\Tool;
use Stringable;

/**
 * Tool: Summarize Conversation
 *
 * Summarizes a past AI agent conversation session for internal staff.
 */
class SummarizeConversation implements Tool
{
    public function name(): Stringable|string
    {
        return 'summarize_conversation';
    }

    public function description(): Stringable|string
    {
        return 'Retrieve and summarize a past AI conversation session. Provide the session ID or search by agent name to find and summarize conversations.';
    }

    public function parameters(): array
    {
        return [
            'session_id' => [
                'type'        => 'integer',
                'description' => 'ID of the conversation session to summarize',
                'required'    => false,
            ],
            'agent_name' => [
                'type'        => 'string',
                'description' => 'Filter sessions by agent name',
                'required'    => false,
            ],
        ];
    }

    public function handle(int $session_id = 0, string $agent_name = ''): string
    {
        $userId  = auth()->id();
        $plantId = session('active_plant_id');

        if ($session_id > 0) {
            $history = AgentChatHistory::find($session_id);

            if (!$history) {
                return "No conversation found with ID {$session_id}.";
            }

            if ($history->user_id !== $userId && !auth()->user()?->isSystemAdmin()) {
                return "You do not have permission to view that conversation.";
            }

            return $this->formatSummary($history);
        }

        // List recent sessions
        $query = AgentChatHistory::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->take(5);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        if (!empty($agent_name)) {
            $query->where('agent_name', 'LIKE', "%{$agent_name}%");
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            return "No conversation sessions found.";
        }

        $result = "Recent conversation sessions:\n\n";
        foreach ($sessions as $session) {
            $result .= "• **Session #{$session->id}** — Agent: {$session->agent_name}\n";
            $result .= "  Date: " . $session->created_at->format('d M Y H:i') . "\n";
            $result .= "  Messages: {$session->message_count}\n";
            if ($session->summary) {
                $result .= "  Summary: {$session->summary}\n";
            }
            $result .= "\n";
        }

        $result .= "Use `session_id` to get a full summary of a specific session.";

        return $result;
    }

    private function formatSummary(AgentChatHistory $history): string
    {
        $messages = $history->messages ?? [];
        $result   = "**Conversation Session #{$history->id}**\n";
        $result  .= "Agent: {$history->agent_name}\n";
        $result  .= "Date: " . $history->created_at->format('d M Y H:i') . "\n";
        $result  .= "Messages: " . count($messages) . "\n\n";

        if ($history->summary) {
            $result .= "**Summary:** {$history->summary}\n\n";
        }

        $result .= "**Conversation Transcript:**\n";
        foreach (array_slice($messages, 0, 10) as $msg) {
            $role    = ucfirst($msg['role'] ?? 'unknown');
            $text    = mb_substr($msg['text'] ?? '', 0, 200);
            $result .= "\n**{$role}:** {$text}";
            if (mb_strlen($msg['text'] ?? '') > 200) {
                $result .= '...';
            }
        }

        if (count($messages) > 10) {
            $result .= "\n\n_(Showing first 10 of " . count($messages) . " messages)_";
        }

        return $result;
    }
}
