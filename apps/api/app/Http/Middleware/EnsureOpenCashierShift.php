<?php

namespace App\Http\Middleware;

use App\Models\CashierShift;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOpenCashierShift
{
    /**
     * Require both cashiers and administrators to have an open
     * cash register before completing routes protected by shift.open.
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        $hasOpenRegister =
            CashierShift::query()
            ->where(
                'cashier_id',
                $user->id,
            )
            ->where(
                'status',
                CashierShift::STATUS_OPEN,
            )
            ->exists();

        if (!$hasOpenRegister) {
            return response()->json([
                'message' =>
                'Open your cash register before completing this transaction.',
            ], 422);
        }

        return $next($request);
    }
}
