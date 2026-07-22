<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RevenueCatWebhookController extends Controller
{
    private const ACTIVE_EVENTS = ['INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE', 'TRANSFER'];

    // Solo EXPIRATION termina el acceso. CANCELLATION significa "apagó la
    // auto-renovación": el usuario pagó hasta expiration_at y conserva premium
    // hasta entonces (hasPremiumAccess() ya valida subscription_expires_at).
    private const INACTIVE_EVENTS = ['EXPIRATION'];

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.revenuecat.webhook_secret');
        $header = (string) $request->header('Authorization', '');
        if (! $secret || ! hash_equals((string) $secret, $header)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event', []);
        $type = $event['type'] ?? null;

        // TRANSFER events move an anonymous purchase onto an identified user
        // (e.g. a purchase made before login); the destination is in
        // transferred_to, not app_user_id.
        $transferredTo = $event['transferred_to'] ?? null;
        $appUserId = ($type === 'TRANSFER' && is_array($transferredTo) && ! empty($transferredTo))
            ? end($transferredTo)
            : ($event['app_user_id'] ?? null);

        if (! $type || ! $appUserId) {
            return response()->json(['status' => 'ignored']);
        }

        // Buscar por id solo cuando app_user_id es estrictamente numérico:
        // MySQL castea '15-legacy' a 15 en comparaciones bigint=string, lo que
        // podría acreditar la suscripción al usuario equivocado.
        $aliases = is_array($event['aliases'] ?? null) ? $event['aliases'] : [];
        $candidateIds = array_values(array_unique(array_filter([
            $appUserId,
            ...$aliases,
            ...(is_array($transferredTo) ? $transferredTo : []),
        ], fn ($id) => is_string($id) || is_int($id))));
        $numericIds = array_map(
            'intval',
            array_filter($candidateIds, fn ($id) => ctype_digit((string) $id))
        );

        $user = User::whereIn('revenuecat_customer_id', $candidateIds)
            ->when(! empty($numericIds), fn ($q) => $q->orWhereIn('id', $numericIds))
            ->first();

        if (! $user) {
            report(new \RuntimeException("RevenueCat webhook: no user found for app_user_id={$appUserId}"));
            return response()->json(['status' => 'user_not_found']);
        }

        if (! $user->revenuecat_customer_id) {
            $identifiedId = collect($candidateIds)
                ->first(fn ($id) => ctype_digit((string) $id) && (int) $id === $user->id);
            $user->revenuecat_customer_id = (string) ($identifiedId ?? $appUserId);
        }

        if (in_array($type, self::ACTIVE_EVENTS, true)) {
            $user->subscription_status = 'premium';
            if (! empty($event['expiration_at_ms'])) {
                $user->subscription_expires_at = Carbon::createFromTimestampMs($event['expiration_at_ms']);
            }
        } elseif (in_array($type, self::INACTIVE_EVENTS, true)) {
            $user->subscription_status = 'free';
            $user->subscription_expires_at = null;
        } elseif ($type === 'CANCELLATION') {
            // No-op deliberado: conserva premium hasta subscription_expires_at.
            // Si RevenueCat envía una expiración actualizada, respétala.
            if (! empty($event['expiration_at_ms'])) {
                $user->subscription_expires_at = Carbon::createFromTimestampMs($event['expiration_at_ms']);
            }
        } else {
            report(new \RuntimeException("RevenueCat webhook: unhandled event type={$type}"));
        }

        $user->save();

        return response()->json(['status' => 'ok']);
    }
}
