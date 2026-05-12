<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SimilarCaseSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RagSearchController extends Controller
{
    public function __invoke(Request $request, SimilarCaseSearch $search): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $query = $validated['query'] ?? $validated['message'] ?? '';
        $limit = $validated['limit'] ?? 3;

        return response()->json([
            'similar_cases' => $search->search($query, $limit),
        ]);
    }
}
