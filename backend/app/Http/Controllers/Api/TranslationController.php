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
        $query = Translation::orderBy('sort_order');

        if ($lang = $request->query('language')) {
            $query->where('language', $lang);
        }

        return TranslationResource::collection($query->get());
    }
}
