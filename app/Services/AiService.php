<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Use AI (ChatGPT or Gemini) to analyze error logs and provide a diagnosis.
     */
    public function diagnoseError(array $errorContext): string
    {
        $openaiKey = env('OPENAI_API_KEY');
        $geminiKey = env('GEMINI_API_KEY');

        $prompt = "You are an expert technical support engineer for a Concrete Batching Plant. 
        An operator is having trouble capturing weight from a weighbridge. The system failed 5 consecutive times.
        
        Analyze these technical logs and provide:
        1. A 'Diagnosis' of what is likely wrong.
        2. Three 'Recommended Actions' for the technician on site.
        
        TECHNICAL LOGS:
        " . json_encode($errorContext, JSON_PRETTY_PRINT) . "
        
        Keep the response professional, concise, and easy to read in an email.";

        // Prefer ChatGPT (OpenAI) if key is available
        if ($openaiKey) {
            return $this->diagnoseWithOpenAI($prompt, $openaiKey);
        }

        // Fallback to Gemini
        if ($geminiKey) {
            return $this->diagnoseWithGemini($prompt, $geminiKey);
        }

        return "AI Diagnosis unavailable: No API keys configured in .env (OPENAI_API_KEY or GEMINI_API_KEY).";
    }

    /**
     * Diagnosis using ChatGPT (OpenAI)
     */
    private function diagnoseWithOpenAI(string $prompt, string $apiKey): string
    {
        try {
            $response = Http::withToken($apiKey)->timeout(12)->post("https://api.openai.com/v1/chat/completions", [
                'model' => 'gpt-4o', // Using gpt-4o for best results
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a professional industrial support assistant.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.4,
                'max_tokens' => 512
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content') ?? "ChatGPT could not generate a diagnosis.";
            }

            Log::error('OpenAI Diagnosis API failed', ['response' => $response->body()]);
            return "ChatGPT Diagnosis failed (API Error).";
        } catch (\Exception $e) {
            Log::error('OpenAI Diagnosis Exception', ['message' => $e->getMessage()]);
            return "ChatGPT Diagnosis failed due to a connection error.";
        }
    }

    /**
     * Diagnosis using Gemini
     */
    private function diagnoseWithGemini(string $prompt, string $apiKey): string
    {
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(10)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text') ?? "Gemini could not determine the cause.";
            }

            Log::error('Gemini Diagnosis API failed', ['response' => $response->body()]);
            return "Gemini Diagnosis failed (API Error).";
        } catch (\Exception $e) {
            Log::error('Gemini Diagnosis Exception', ['message' => $e->getMessage()]);
            return "Gemini Diagnosis failed due to a connection error.";
        }
    }
}
