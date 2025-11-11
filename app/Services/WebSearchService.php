<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
class WebSearchService
{
    public function search($query)
    {
        $apiKeys = [
            env('RAPIDAPI_KEY'),
            env('FALLBACK1_RAPIDAPI_KEY'),
            env('FALLBACK2_RAPIDAPI_KEY'),
            env('FALLBACK3_RAPIDAPI_KEY'),
            env('FALLBACK4_RAPIDAPI_KEY'),
            env('FALLBACK5_RAPIDAPI_KEY'),
        ];
    
        foreach ($apiKeys as $apiKey) {
            if (Cache::get("rapidapi_key_{$apiKey}_blocked")) {
                Log::info("🔁 Skipping blocked API key: {$apiKey}");
                continue;
            }
    
            $result = $this->callSearchApi($query, $apiKey, 'google-search74.p.rapidapi.com');
    
            // If result is array, we got valid results
            if (is_array($result)) {
                return $result;
            }
    
            // If rate-limited or failed, it’s already cached inside callSearchApi
        }
    
        return '❌ No results found from any search provider.';
    }
    



    protected function callSearchApi($query, $apiKey, $host)
    {
        // Skip if this API key is temporarily blocked due to rate limit
        if (Cache::get("rapidapi_key_{$apiKey}_blocked")) {
            return "⚠️ Skipping known-rate-limited API key.";
        }
    
        try {
            $response = Http::withHeaders([
                'X-RapidAPI-Key' => $apiKey,
                'X-RapidAPI-Host' => $host,
            ])->timeout(3) // Quick timeout for responsiveness
              ->get("https://{$host}/", [
                  'query' => $query,
                  'limit' => 5,
                  'related_keywords' => true,
              ]);
    
            Log::info("Search API response from {$host}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
    
            // Detect common rate-limit response
            if ($response->status() === 429 || str_contains(strtolower($response->body()), 'rate limit')) {
                Cache::put("rapidapi_key_{$apiKey}_blocked", true, now()->addDays(20));
                return "⚠️ Rate limit reached.";
            }
    
            if (!$response->successful()) {
                return "⚠️ Web search failed (status: {$response->status()})";
            }
    
            $data = $response->json();
            $results = $data['results'] ?? [];
    
            if (empty($results)) {
                return "❌ No results found.";
            }
    
            $finalResults = [];
    
            foreach (array_slice($results, 0, 5) as $result) {
                $url = $result['link'] ?? $result['url'] ?? '#';
                $detailedContent = $this->getPageContent($url);
    
                $finalResults[] = [
                    'title' => $result['title'] ?? 'No title',
                    'description' => $detailedContent,
                    'link' => $url,
                ];
            }
    
            return $finalResults;
        } catch (\Exception $e) {
            Log::error("Search API exception: " . $e->getMessage());
            return "⚠️ Error calling search API: " . $e->getMessage();
        }
    }
    

    protected function getPageContent($url)
    {
        try {
            // Use HTTP to fetch the page content
            $pageResponse = Http::get($url);

            // If the page fetch was successful, parse and extract the content
            if ($pageResponse->successful()) {
                $content = $pageResponse->body();

                // Create a new DOMDocument object to parse the HTML content
                $dom = new \DOMDocument();
                @$dom->loadHTML($content); // Suppress warnings from badly formed HTML

                // Use DOMXpath to search for specific elements in the document
                $xpath = new \DOMXPath($dom);

                // Example: Search for the first <p> tag in the content
                $paragraphs = $xpath->query('//p'); // Get all <p> elements

                $text = '';
                foreach ($paragraphs as $p) {
                    $text .= $p->nodeValue . "\n"; // Concatenate text from each <p>
                }

                // Check if we found any text
                if (!empty($text)) {
                    return substr($text, 0, 5000);  // Return the first 1000 characters or whatever length you prefer
                } else {
                    return "⚠️ Couldn't extract more detailed content.";
                }
            }

            return "⚠️ Failed to fetch the full page content.";
        } catch (\Exception $e) {
            // Handle errors (e.g., connection issues, invalid URL)
            return "⚠️ Error fetching page content: " . $e->getMessage();
        }
    }


    public function searchPersonProfile(string $name): string
    {
        return "🔎 I couldn't find *{$name}* in our records, but I found something on the web:\n[Search Results for {$name}](https://www.google.com/search?q=" . urlencode($name) . ")";
    }


    public function analyzeWebSearch(array $searchResults, string $userQuestion): string

    {
        $combinedText = "";

        foreach ($searchResults as $result) {
            $title = $result['title'] ?? 'No title';
            $desc = $result['description'] ?? 'No description';
            $link = $result['link'] ?? '#';

            $combinedText .= "Title: {$title}\nDescription: {$desc}\nLink: {$link}\n\n";
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a knowledgeable, confident assistant. Deliver the following web results clearly, directly, and assertively. Provide a precise and helpful answer to the user’s question, without referring to any sources. Present the information as though you fully understand and are an expert in the field.'
            ],
            [
                'role' => 'user',
                'content' => "User’s question: “{$userQuestion}”\n\nSearch results:\n{$combinedText}"
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4.1-mini',
            'messages' => $messages,
        ]);

        return $response->json('choices.0.message.content');
    }

    public function handleSearchAndAnalyze(string $userMessage, string $userName): string
    {
        if (strpos(strtolower($userMessage), 'search the internet') !== false) {
            $query = str_replace('search the internet for', '', strtolower($userMessage));
            $query = trim($query);

            $searchResult = $this->search($query);

            if (is_array($searchResult) && isset($searchResult[0])) {
                $rephrasedContent = $this->analyzeWebSearch($searchResult, $query);

                $linkIcons = '';
                foreach ($searchResult as $result) {
                    $url = $result['link'] ?? null;
                    $title = $result['title'] ?? 'Source';
                    if ($url) {
                        $host = parse_url($url, PHP_URL_HOST);
                        $faviconUrl = "https://www.google.com/s2/favicons?domain={$host}&sz=32";

                        $linkIcons .= "<a href=\"{$url}\" target=\"_blank\" title=\"{$title}\" style=\"margin-right:8px; display: inline-block; transition: transform 0.3s ease-in-out; text-decoration: none;\">"
                            . "<img src=\"{$faviconUrl}\" alt=\"{$host}\" style=\"width:24px; height:24px; border-radius:50%; vertical-align:middle; border: 1px solid #ccc; padding: 2px; transition: transform 0.3s ease-in-out;\" />"
                            . "</a>";
                    }
                }

                return "🔎 Here's what I found on the internet for <strong>\"{$query}\"</strong>:<br><br>"
                    . nl2br(e($rephrasedContent)) // escape HTML from LLM
                    . "<br><br><strong>Sources:</strong> "
                    . "<div style=\"display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-top: 8px;\">"
                    . $linkIcons // Dynamically generated links with icons
                    . "</div>";
            } else {
                return "❌ Sorry, I couldn’t find any results for your query.\n\n" .
                    "💡 Try rephrasing your question or be more specific. For example:\n" .
                    "- *Search the internet for the latest AI trends in 2025*\n" .
                    "- *Search the internet for best PHP frameworks*\n" .
                    "- *Search the internet for DICT CAR accomplishments 2024*";
            }
        } else {
            return "🌐 To trigger an internet search, please use a phrase like:\n" .
                "- *Search the internet for climate change updates*\n" .
                "- *Search the internet for recent tech news*\n\n" .
                "How else can I assist you today?";
        }
    }
}
