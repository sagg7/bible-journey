<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TranslationResource;
use App\Models\Translation;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function index(Request $request)
    {
        $includeTestOnly = (bool) ($request->user('sanctum')?->has_test_access);

        $query = Translation::orderBy('sort_order')
            ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false));

        if ($lang = $request->query('language')) {
            $query->where('language', $lang);
        }

        return TranslationResource::collection($query->get());
    }
}
