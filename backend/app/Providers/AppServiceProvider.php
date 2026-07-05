<?php

namespace App\Providers;

use App\Models\Institution;
use App\Services\Ezra\AnthropicClient;
use App\Services\Ezra\LlmClientInterface;
use App\Services\Ezra\OpenAICompatibleClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmClientInterface::class, function () {
            return match (config('ezra.provider')) {
                'anthropic' => new AnthropicClient(),
                default     => new OpenAICompatibleClient(),
            };
        });
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Institution::class);

        // Public institution-signup form: creates DB rows + a real Stripe Checkout
        // session per request, so it needs its own (tighter than default) throttle.
        RateLimiter::for('institution-signup', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });
    }
}
