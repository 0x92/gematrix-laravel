<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = true;
        $cacheOk = true;

        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        try {
            Cache::put('healthcheck', true, 10);
            $cacheOk = Cache::get('healthcheck') === true;
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $ok = $dbOk && $cacheOk;

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'db' => $dbOk,
            'cache' => $cacheOk,
            'timestamp' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
