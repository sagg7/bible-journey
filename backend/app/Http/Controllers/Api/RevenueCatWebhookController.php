<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RevenueCatWebhookController extends Controller
{
    private const ACTIVE_EVENTS = ['INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE'];
    private const INACTIVE_EVENTS = ['EXPIRATION', 'CANCELLATION'];

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.revenuecat.webhook_secret');
        if (! $secret || $request->header('Authorization') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event', []);
        $type = $event['type'] ?? null;
        $appUserId = $event['app_user_id'] ?? null;

        if (! $type || ! $appUserId) {
            return response()->json(['status' => 'ignored']);
        }

        $user = User::where('revenuecat_customer_id', $appUserId)
            ->orWhere('id', $appUserId)
            ->first();

        if (! $user) {
            report(new \RuntimeException("RevenueCat webhook: no user found for app_user_id={$appUserId}"));
            return response()->json(['status' => 'user_not_found']);
        }

        if (! $user->revenuecat_customer_id) {
            $user->revenuecat_customer_id = $appUserId;
        }

        if (in_array($type, self::ACTIVE_EVENTS, true)) {
            $user->subscription_status = 'premium';
            if (! empty($event['expiration_at_ms'])) {
                $user->subscription_expires_at = Carbon::createFromTimestampMs($event['expiration_at_ms']);
            }
        } elseif (in_array($type, self::INACTIVE_EVENTS, true)) {
            $user->subscription_status = 'free';
            $user->subscription_expires_at = null;
        } else {
            report(new \RuntimeException("RevenueCat webhook: unhandled event type={$type}"));
        }

        $user->save();

        return response()->json(['status' => 'ok']);
    }
}
