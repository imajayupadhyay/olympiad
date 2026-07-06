<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    /**
     * Autocomplete for the registration school field. Public (registration is
     * unauthenticated). Returns up to 10 matches with their address so the form
     * can auto-fill the school address on selection.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $schools = School::search($term)
            ->limit(10)
            ->get(['id', 'name', 'address']);

        return response()->json(['success' => true, 'data' => $schools]);
    }
}
