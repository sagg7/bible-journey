<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\RouteDetailResource;
use App\Http\Resources\Api\RouteListResource;
use App\Models\Route;

class RouteController extends Controller
{
    public function index()
    {
        $routes = Route::with('translations')
            ->withCount('events')
            ->orderBy('sort_order')
            ->get();

        return RouteListResource::collection($routes);
    }

    public function show(Route $route)
    {
        $route->load([
            'translations',
            'events' => fn ($q) => $q->with('translations'),
        ]);

        return new RouteDetailResource($route);
    }
}
