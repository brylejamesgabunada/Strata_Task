<?php

namespace App\Services;

use App\Models\PastEnquiry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SimilarCaseSearch
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 3): Collection
    {
        $limit = max(1, min($limit, 5));
        $queryLower = Str::lower($query);
        $tokens = $this->tokens($queryLower);

        return PastEnquiry::query()
            ->get()
            ->map(function (PastEnquiry $case) use ($queryLower, $tokens) {
                return $this->formatCase($case, $this->score($case, $queryLower, $tokens));
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        $stopWords = [
            'about', 'after', 'again', 'all', 'and', 'any', 'are', 'been', 'but', 'can',
            'could', 'did', 'for', 'from', 'get', 'had', 'has', 'have', 'how', 'into',
            'its', 'just', 'lot', 'not', 'now', 'our', 'out', 'over', 'please', 'that',
            'the', 'their', 'them', 'there', 'they', 'this', 'was', 'what', 'when', 'who',
            'why', 'will', 'with', 'would', 'you', 'your',
        ];

        return collect(preg_split('/[^a-z0-9-]+/', $text) ?: [])
            ->filter(fn (string $token) => strlen($token) > 2 && ! in_array($token, $stopWords, true))
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $tokens
     */
    private function score(PastEnquiry $case, string $queryLower, array $tokens): int
    {
        $haystack = Str::lower(implode(' ', array_filter([
            $case->category,
            $case->subcategory,
            $case->summary,
            $case->original_message,
            $case->recommended_action,
            $case->suggested_response,
            $case->previous_resolution,
            $case->page_content,
            json_encode($case->metadata ?? []),
        ])));

        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += 2;
            }
        }

        if ($this->containsAny($queryLower, ['unhappy', 'unacceptable', 'complaint', 'nothing has been done']) && $case->category === 'Complaint') {
            $score += 6;
        }

        if ($this->containsAny($queryLower, ['leak', 'water', 'lift', 'broken', 'urgent', 'emergency', 'safety']) && $case->category === 'Maintenance Request') {
            $score += 6;
        }

        if ($this->containsAny($queryLower, ['levy', 'invoice', 'budget', 'payment', 'overdue']) && $case->category === 'Financial Enquiry') {
            $score += 6;
        }

        if ($this->containsAny($queryLower, ['by-law', 'bylaw', 'pet', 'agm', 'vote', 'tribunal']) && $case->category === 'Legal or Compliance Enquiry') {
            $score += 6;
        }

        if ($this->containsAny($queryLower, ['change strata', 'switch', 'new manager', 'service include', 'proposal']) && $case->category === 'New Client Enquiry') {
            $score += 6;
        }

        return $score;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCase(PastEnquiry $case, int $score): array
    {
        return [
            'inquiry_id' => $case->inquiry_id,
            'category' => $case->category,
            'subcategory' => $case->subcategory,
            'urgency' => $case->urgency,
            'client_status' => $case->client_status,
            'summary' => $case->summary,
            'original_message' => $case->original_message,
            'recommended_action' => $case->recommended_action,
            'suggested_response' => $case->suggested_response,
            'previous_resolution' => $case->previous_resolution,
            'score' => $score,
        ];
    }
}
