<?php

namespace App\Http\Middleware;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckRestaurantSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedHost = config('tenant.api_host');

        if (!$expectedHost && app()->environment('production')) {
            return response()->json(['error' => 'subscription_not_configured'], 503);
        }

        if ($expectedHost && strcasecmp(rtrim($request->getHost(), '.'), rtrim($expectedHost, '.')) !== 0) {
            return response()->json(['error' => 'unknown_host'], 404);
        }

        try {
            $expiresOn = DB::table('restaurant_subscription')->where('id', 1)->value('expires_on');
        } catch (QueryException $exception) {
            report($exception);

            return response()->json(['error' => 'subscription_unavailable'], 503);
        }

        if (!$expiresOn) {
            if (!app()->environment('production')) {
                return $next($request);
            }

            return response()->json(['error' => 'subscription_not_configured'], 503);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresOn, new DateTimeZone('UTC'));

        if (!$date || $date->format('Y-m-d') !== $expiresOn) {
            return response()->json(['error' => 'subscription_unavailable'], 503);
        }

        if (now('UTC')->toDateString() > $expiresOn) {
            return response()->json(['error' => 'subscription_expired'], 403);
        }

        return $next($request);
    }
}
