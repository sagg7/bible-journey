<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProgressResource;
use App\Models\HistoricalEvent;
use App\Models\Route;
use App\Models\UserProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProgressController extends Controller
{
    public function show(Request $request)
    {
        $route = $this->resolveRoute($request);
        $progress = $this->progressFor($request, $route);

        return new ProgressResource($progress->load('route'));
    }

    public function complete(Request $request)
    {
        $data = $request->validate([
            'route_slug' => ['required', 'string', 'exists:routes,slug'],
            'event_slug' => ['required', 'string', 'exists:historical_events,slug'],
        ]);

        $route = Route::where('slug', $data['route_slug'])->firstOrFail();
        $event = HistoricalEvent::where('slug', $data['event_slug'])->firstOrFail();
        $progress = $this->progressFor($request, $route);

        $completed = $progress->completed_events ?? [];
        if (! in_array($event->id, $completed, true)) {
            $completed[] = $event->id;
        }

        $progress->completed_events = array_values($completed);
        $progress->current_event_id = $event->id;
        $progress->streak_count = $this->recomputeStreak($progress);
        $progress->last_activity_date = Carbon::today();
        $progress->save();

        return new ProgressResource($progress->load('route'));
    }

    private function resolveRoute(Request $request): Route
    {
        $slug = $request->query('route', 'vida-de-david');

        return Route::where('slug', $slug)->firstOrFail();
    }

    private function progressFor(Request $request, Route $route): UserProgress
    {
        return UserProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'route_id' => $route->id],
            ['streak_count' => 0, 'completed_events' => []]
        );
    }

    private function recomputeStreak(UserProgress $progress): int
    {
        $today = Carbon::today();
        $last = $progress->last_activity_date;

        if (! $last) {
            return 1;
        }
        if ($last->isSameDay($today)) {
            return max(1, $progress->streak_count);
        }
        if ($last->isSameDay($today->copy()->subDay())) {
            return $progress->streak_count + 1;
        }

        return 1; // se rompió la racha
    }
}
