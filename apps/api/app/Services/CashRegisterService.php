<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    /**
     * Find the currently open cash register for a user.
     *
     * The existing POS stores cash-register sessions in cashier_shifts.
     * The cashier_id column points to users.id and can therefore also
     * hold an administrator user when the administrator opens a register.
     */
    public function findOpenRegisterForUser(
        User $user,
        bool $lockForUpdate = false,
    ): ?object {
        $query = DB::table('cashier_shifts')
            ->where(
                'cashier_id',
                $user->id,
            )
            ->where(
                'status',
                'open',
            )
            ->orderByDesc('opened_at')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Get the user's open register or fail the whole transaction.
     */
    public function requireOpenRegisterForUser(
        User $user,
        bool $lockForUpdate = false,
    ): object {
        $register =
            $this->findOpenRegisterForUser(
                $user,
                $lockForUpdate,
            );

        if (!$register) {
            throw ValidationException::withMessages([
                'payments' => [
                    'Open your cash register before recording a cash payment.',
                ],
            ]);
        }

        return $register;
    }

    /**
     * Record physical cash paid to a supplier as a drawer cash-out.
     *
     * Card, bank-transfer and cheque payments must never call this method.
     */
    public function recordSupplierCashOut(
        User $user,
        Purchase $purchase,
        float $amount,
    ): void {
        $amount = round(
            $amount,
            2,
        );

        if ($amount <= 0) {
            return;
        }

        $register =
            $this->requireOpenRegisterForUser(
                $user,
                true,
            );

        DB::table('cash_register_movements')
            ->insert([
                'cashier_shift_id' =>
                (int) $register->id,

                'movement_type' =>
                'cash_out',

                'reason' =>
                'other',

                'amount' =>
                $amount,

                'description' =>
                sprintf(
                    'Supplier cash payment for purchase %s.',
                    $purchase->purchase_number,
                ),

                'reference_number' =>
                $purchase->purchase_number,

                'created_by' =>
                $user->id,

                'created_at' =>
                now(),

                'updated_at' =>
                now(),
            ]);
    }
}
