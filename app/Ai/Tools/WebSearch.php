<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Http;
use Stringable;

class WebSearch implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the web to retrieve live, current online information about Onemodo Technologies, developers, company details, registered addresses, social profiles, or general facts.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = $request->input('query');
        if (empty($query)) {
            return 'Please specify a search query.';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->get('https://html.duckduckgo.com/html/', [
                'q' => $query
            ]);

            if (!$response->successful()) {
                return 'Web search request failed.';
            }

            $html = $response->body();
            $results = [];

            // Extract result divs
            preg_match_all('/<div class="result\s+results_links[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/is', $html, $divMatches);

            if (!empty($divMatches[0])) {
                foreach (array_slice($divMatches[0], 0, 5) as $index => $div) {
                    preg_match('/<a class="result__url"[^>]* href="([^"]+)"[^>]*>(.*?)<\/a>/is', $div, $urlMatch);
                    preg_match('/<a class="result__snippet"[^>]*>(.*?)<\/a>/is', $div, $snippetMatch);

                    $title = isset($urlMatch[2]) ? strip_tags(trim($urlMatch[2])) : 'Result ' . ($index + 1);
                    $url = isset($urlMatch[1]) ? urldecode(trim($urlMatch[1])) : '';

                    // Clean redirect URL if present
                    if (str_contains($url, 'uddg=')) {
                        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
                        if (isset($queryParams['uddg'])) {
                            $url = $queryParams['uddg'];
                        }
                    }

                    $snippet = isset($snippetMatch[1]) ? strip_tags(trim($snippetMatch[1])) : '';

                    if ($snippet) {
                        $results[] = "[Source: {$title}] ({$url})\n{$snippet}";
                    }
                }
            }

            if (empty($results)) {
                // Fallback: parse simple result snippets
                preg_match_all('/<a class="result__snippet"[^>]*>(.*?)<\/a>/is', $html, $matches);
                if (!empty($matches[1])) {
                    foreach (array_slice($matches[1], 0, 5) as $snippet) {
                        $results[] = "- " . html_entity_decode(strip_tags(trim($snippet)));
                    }
                }
            }

            if (empty($results)) {
                return "No search results found for: '{$query}'.";
            }

            return "Web Search results for '{$query}':\n\n" . implode("\n\n", $results);

        } catch (\Exception $e) {
            return "Web search error: " . $e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search query to search on the web.')->required(),
        ];
    }
}
