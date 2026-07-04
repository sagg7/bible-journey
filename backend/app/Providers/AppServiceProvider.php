<?php

namespace App\Providers;

use App\Models\Institution;
use App\Services\Ezra\AnthropicClient;
use App\Services\Ezra\LlmClientInterface;
use App\Services\Ezra\OpenAICompatibleClient;
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
    }
}
