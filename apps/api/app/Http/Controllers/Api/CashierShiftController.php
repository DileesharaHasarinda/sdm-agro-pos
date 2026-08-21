<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\CloseCashierShiftRequest;
use App\Http\Requests\Shift\OpenCashierShiftRequest;
use App\Http\Requests\Shift\StoreCashMovementRequest;
use App\Models\CashierShift;
use App\Models\CashRegisterMovement;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashierShiftController extends Controller
{
    /*
     * =========================================================
     * CURRENT CASH REGISTER
     * =========================================================
     */

    public function current(
        Request $request,
    ): JsonResponse {
        $user =
            $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        /*
         * Both Admin and Cashier can have
         * their own Cash Register.
         */
        if (
            ! $this->canUseCashRegister(
                $user,
            )
        ) {
            return response()->json([
                'message' =>
                'You are not allowed to access the cash register.',
            ], 403);
        }

        $shift =
            CashierShift::query()
            ->where(
                'cashier_id',
                $user->id,
            )
            ->where(
                'status',
                CashierShift::STATUS_OPEN,
            )
            ->latest(
                'opened_at',
            )
            ->latest(
                'id',
            )
            ->first();

        return response()->json([
            'data' =>
            $shift
                ? $this->shiftData(
                    $shift,
                    true,
                )
                : null,
        ]);
    }

    /*
     * =========================================================
     * CASH REGISTER HISTORY
     * =========================================================
     */

    public function index(
        Request $request,
    ): JsonResponse {
        $user =
            $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        if (
            ! $this->canUseCashRegister(
                $user,
            )
        ) {
            return response()->json([
                'message' =>
                'You are not allowed to access cash register history.',
            ], 403);
        }

        $validated =
            $request->validate([
                'search' => [
                    'nullable',
                    'string',
                    'max:160',
                ],

                /*
                 * Admin can filter register history
                 * by user.
                 */
                'cashier_id' => [
                    'nullable',
                    'integer',
                    'exists:users,id',
                ],

                'status' => [
                    'nullable',

                    Rule::in([
                        'all',
                        CashierShift::STATUS_OPEN,
                        CashierShift::STATUS_CLOSED,
                    ]),
                ],

                'date_from' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],

                'date_to' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:date_from',
                ],

                'page' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'per_page' => [
                    'nullable',
                    'integer',
                    'min:5',
                    'max:100',
                ],
            ]);

        $search =
            trim(
                (string) (
                    $validated['search']
                    ?? ''
                ),
            );

        /*
         * Cashier:
         * only their own history.
         *
         * Admin:
         * all history unless cashier_id
         * is supplied.
         */
        $cashierId =
            $user->isCashier()
            ? $user->id
            : (
                $validated['cashier_id']
                ?? null
            );

        $status =
            $validated['status']
            ?? 'all';

        $dateFrom =
            $validated['date_from']
            ?? null;

        $dateTo =
            $validated['date_to']
            ?? null;

        $perPage =
            (int) (
                $validated['per_page']
                ?? 20
            );

        $query =
            CashierShift::query()
            ->with([
                'cashier:id,name,username,email,phone',

                'openedBy:id,name,username',

                'closedBy:id,name,username',
            ])
            ->when(
                $cashierId !== null,

                fn(
                    Builder $shiftQuery,
                ) =>
                $shiftQuery->where(
                    'cashier_id',
                    $cashierId,
                ),
            )
            ->when(
                $status !== 'all',

                fn(
                    Builder $shiftQuery,
                ) =>
                $shiftQuery->where(
                    'status',
                    $status,
                ),
            )
            ->when(
                $dateFrom !== null,

                fn(
                    Builder $shiftQuery,
                ) =>
                $shiftQuery->whereDate(
                    'opened_at',
                    '>=',
                    $dateFrom,
                ),
            )
            ->when(
                $dateTo !== null,

                fn(
                    Builder $shiftQuery,
                ) =>
                $shiftQuery->whereDate(
                    'opened_at',
                    '<=',
                    $dateTo,
                ),
            )
            ->when(
                $search !== '',

                function (
                    Builder $shiftQuery,
                ) use (
                    $search,
                ): void {
                    $shiftQuery->where(
                        function (
                            Builder $searchQuery,
                        ) use (
                            $search,
                        ): void {
                            $searchQuery
                                ->where(
                                    'shift_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereHas(
                                    'cashier',

                                    function (
                                        Builder $cashierQuery,
                                    ) use (
                                        $search,
                                    ): void {
                                        $cashierQuery
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'username',
                                                'like',
                                                "%{$search}%",
                                            );
                                    },
                                );
                        },
                    );
                },
            )
            ->latest(
                'opened_at',
            )
            ->latest(
                'id',
            );

        /*
         * =====================================================
         * SUMMARY
         * =====================================================
         */

        $summaryRows =
            (clone $query)
            ->get();

        $summary = [
            'total_shifts' =>
            $summaryRows
                ->count(),

            'open_shifts' =>
            $summaryRows
                ->where(
                    'status',
                    CashierShift::STATUS_OPEN,
                )
                ->count(),

            'closed_shifts' =>
            $summaryRows
                ->where(
                    'status',
                    CashierShift::STATUS_CLOSED,
                )
                ->count(),

            'total_opening_cash' =>
            (float) $summaryRows
                ->sum(
                    'opening_cash',
                ),

            'total_expected_cash' =>
            (float) $summaryRows
                ->sum(
                    fn(
                        CashierShift $shift,
                    ): float =>
                    (float) $this
                        ->calculateTotals(
                            $shift,
                        )['expected_cash'],
                ),

            'total_difference' =>
            (float) $summaryRows
                ->where(
                    'status',
                    CashierShift::STATUS_CLOSED,
                )
                ->sum(
                    'cash_difference',
                ),
        ];

        /*
         * =====================================================
         * PAGINATION
         * =====================================================
         */

        $shifts =
            $query->paginate(
                $perPage,
            );

        return response()->json([
            'data' =>
            collect(
                $shifts->items(),
            )
                ->map(
                    fn(
                        CashierShift $shift,
                    ): array =>
                    $this->shiftData(
                        $shift,
                        false,
                    ),
                )
                ->values(),

            'summary' =>
            $summary,

            'meta' => [
                'current_page' =>
                $shifts
                    ->currentPage(),

                'last_page' =>
                $shifts
                    ->lastPage(),

                'per_page' =>
                $shifts
                    ->perPage(),

                'total' =>
                $shifts
                    ->total(),

                'from' =>
                $shifts
                    ->firstItem(),

                'to' =>
                $shifts
                    ->lastItem(),
            ],
        ]);
    }

    /*
     * =========================================================
     * CASH REGISTER DETAILS
     * =========================================================
     */

    public function show(
        Request $request,
        CashierShift $cashierShift,
    ): JsonResponse {
        $user =
            $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        if (
            ! $this->canUseCashRegister(
                $user,
            )
        ) {
            return response()->json([
                'message' =>
                'You are not allowed to access the cash register.',
            ], 403);
        }

        /*
         * Admin:
         * can VIEW every register.
         *
         * Cashier:
         * can VIEW only their own register.
         */
        if (
            ! $user->isAdmin()
            && (int) $cashierShift
                ->cashier_id
            !== (int) $user->id
        ) {
            return response()->json([
                'message' =>
                'You cannot view another user’s cash register.',
            ], 403);
        }

        return response()->json([
            'data' =>
            $this->shiftData(
                $cashierShift,
                true,
            ),
        ]);
    }

    /*
     * =========================================================
     * OPEN CASH REGISTER
     * =========================================================
     */

    public function open(
        OpenCashierShiftRequest $request,
    ): JsonResponse {
        $user =
            $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        /*
         * Both Admin and Cashier
         * may open their own register.
         */
        if (
            ! $this->canUseCashRegister(
                $user,
            )
        ) {
            return response()->json([
                'message' =>
                'Only admin and cashier accounts can open a cash register.',
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' =>
                'Your account is inactive.',
            ], 422);
        }

        $validated =
            $request->validated();

        $shift =
            DB::transaction(
                function () use (
                    $user,
                    $validated,
                ): CashierShift {
                    /*
                     * Lock user row.
                     *
                     * This prevents two simultaneous
                     * requests opening two registers
                     * for the same user.
                     */
                    User::query()
                        ->whereKey(
                            $user->id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    /*
                     * Check whether this user already
                     * has an open register.
                     */
                    $existingShift =
                        CashierShift::query()
                        ->where(
                            'cashier_id',
                            $user->id,
                        )
                        ->where(
                            'status',
                            CashierShift::STATUS_OPEN,
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($existingShift) {
                        throw ValidationException::withMessages([
                            'opening_cash' => [
                                'You already have an open cash register.',
                            ],
                        ]);
                    }

                    /*
                     * IMPORTANT:
                     *
                     * Keep cashier_id.
                     *
                     * This is your existing database
                     * column and references users.id.
                     *
                     * Therefore:
                     *
                     * Cashier user ID -> allowed
                     * Admin user ID   -> allowed
                     */
                    $shift =
                        CashierShift::query()
                        ->create([
                            'shift_number' =>
                            null,

                            'cashier_id' =>
                            $user->id,

                            'opening_cash' =>
                            round(
                                (float) $validated['opening_cash'],
                                2,
                            ),

                            'status' =>
                            CashierShift::STATUS_OPEN,

                            'opened_at' =>
                            now(),

                            'opening_notes' =>
                            $validated['opening_notes']
                                ?? null,

                            'opened_by' =>
                            $user->id,
                        ]);

                    /*
                     * Generate shift/register number.
                     */
                    $shift->update([
                        'shift_number' =>
                        sprintf(
                            'SHIFT-%s-%06d',

                            $shift
                                ->opened_at
                                ->format(
                                    'Ymd',
                                ),

                            $shift->id,
                        ),
                    ]);

                    return $shift;
                },
                3,
            );

        return response()->json([
            'message' =>
            'Cash register opened successfully.',

            'data' =>
            $this->shiftData(
                $shift,
                true,
            ),
        ], 201);
    }

    /*
     * =========================================================
     * CLOSE CASH REGISTER
     * =========================================================
     */

    public function close(
        CloseCashierShiftRequest $request,
        CashierShift $cashierShift,
    ): JsonResponse {
        $user =
            $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        if (
            ! $this->canUseCashRegister(
                $user,
            )
        ) {
            return response()->json([
                'message' =>
                'Only admin and cashier accounts can close a cash register.',
            ], 403);
        }

        /*
         * IMPORTANT:
         *
         * Admin can VIEW another cashier's
         * register.
         *
         * But Admin cannot CLOSE another
         * cashier's physical drawer.
         *
         * Both Admin and Cashier close only
         * their OWN register.
         */
        if (
            (int) $cashierShift
                ->cashier_id
            !== (int) $user->id
        ) {
            return response()->json([
                'message' =>
                'You can only close your own cash register.',
            ], 403);
        }

        $validated =
            $request->validated();

        $closedShift =
            DB::transaction(
                function () use (
                    $cashierShift,
                    $validated,
                    $user,
                ): CashierShift {
                    /*
                     * Lock the register before closing.
                     */
                    $lockedShift =
                        CashierShift::query()
                        ->whereKey(
                            $cashierShift->id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    /*
                     * Re-check ownership after lock.
                     */
                    if (
                        (int) $lockedShift
                            ->cashier_id
                        !== (int) $user->id
                    ) {
                        throw ValidationException::withMessages([
                            'actual_cash' => [
                                'You can only close your own cash register.',
                            ],
                        ]);
                    }

                    if (
                        $lockedShift->status
                        !== CashierShift::STATUS_OPEN
                    ) {
                        throw ValidationException::withMessages([
                            'actual_cash' => [
                                'This cash register is already closed.',
                            ],
                        ]);
                    }

                    /*
                     * Recalculate final expected cash
                     * while register row is locked.
                     */
                    $totals =
                        $this->calculateTotals(
                            $lockedShift,
                        );

                    $actualCash =
                        round(
                            (float) $validated['actual_cash'],
                            2,
                        );

                    $expectedCash =
                        round(
                            (float) $totals['expected_cash'],
                            2,
                        );

                    /*
                     * Difference:
                     *
                     * Positive = Excess
                     * Negative = Shortage
                     */
                    $difference =
                        round(
                            $actualCash
                                - $expectedCash,
                            2,
                        );

                    $lockedShift->update([
                        'status' =>
                        CashierShift::STATUS_CLOSED,

                        'closed_at' =>
                        now(),

                        'expected_cash' =>
                        $expectedCash,

                        'actual_cash' =>
                        $actualCash,

                        'cash_difference' =>
                        $difference,

                        'closing_notes' =>
                        $validated['closing_notes']
                            ?? null,

                        'closed_by' =>
                        $user->id,
                    ]);

                    return $lockedShift
                        ->fresh();
                },
                3,
            );

        return response()->json([
            'message' =>
            'Cash register closed successfully.',

            'data' =>
            $this->shiftData(
                $closedShift,
                true,
            ),
        ]);
    }

    /*
     * =========================================================
     * MANUAL CASH IN / CASH OUT
     * =========================================================
     */

    public function storeMovement(
        StoreCashMovementRequest $request,
        CashierShift $cashierShift,
    ): JsonResponse {
        $user =
            $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' =>
                'Unauthenticated.',
            ], 401);
        }

        if (
            ! $this->canUseCashRegister(
                $user,
            )
        ) {
            return response()->json([
                'message' =>
                'Only admin and cashier accounts can record cash movements.',
            ], 403);
        }

        /*
         * Admin may inspect other registers,
         * but manual Cash In / Cash Out must
         * only affect their own drawer.
         */
        if (
            (int) $cashierShift
                ->cashier_id
            !== (int) $user->id
        ) {
            return response()->json([
                'message' =>
                'You can only modify your own cash register.',
            ], 403);
        }

        $validated =
            $request->validated();

        /*
         * Use a transaction + row lock.
         *
         * This prevents a Cash Out being added
         * while another request closes the register.
         */
        $result =
            DB::transaction(
                function () use (
                    $cashierShift,
                    $validated,
                    $user,
                ): array {
                    $lockedShift =
                        CashierShift::query()
                        ->whereKey(
                            $cashierShift->id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        (int) $lockedShift
                            ->cashier_id
                        !== (int) $user->id
                    ) {
                        throw ValidationException::withMessages([
                            'amount' => [
                                'You can only modify your own cash register.',
                            ],
                        ]);
                    }

                    if (
                        $lockedShift->status
                        !== CashierShift::STATUS_OPEN
                    ) {
                        throw ValidationException::withMessages([
                            'amount' => [
                                'Cash movements can only be added to an open cash register.',
                            ],
                        ]);
                    }

                    $movement =
                        CashRegisterMovement::query()
                        ->create([
                            'cashier_shift_id' =>
                            $lockedShift->id,

                            'movement_type' =>
                            $validated['movement_type'],

                            'reason' =>
                            $validated['reason'],

                            'amount' =>
                            round(
                                (float) $validated['amount'],
                                2,
                            ),

                            'description' =>
                            $validated['description'],

                            'reference_number' =>
                            $validated['reference_number']
                                ?? null,

                            'occurred_at' =>
                            now(),

                            'created_by' =>
                            $user->id,
                        ]);

                    return [
                        'movement' =>
                        $movement,

                        'shift' =>
                        $lockedShift,
                    ];
                },
                3,
            );

        /** @var CashRegisterMovement $movement */
        $movement =
            $result['movement'];

        /** @var CashierShift $updatedShift */
        $updatedShift =
            $result['shift'];

        return response()->json([
            'message' =>
            'Cash register movement recorded successfully.',

            'data' => [
                'movement' =>
                $this->movementData(
                    $movement,
                ),

                'shift' =>
                $this->shiftData(
                    $updatedShift
                        ->fresh(),

                    true,
                ),
            ],
        ], 201);
    }

    /*
     * =========================================================
     * CALCULATE REGISTER TOTALS
     * =========================================================
     */

    /**
     * @return array<string, mixed>
     */
    private function calculateTotals(
        CashierShift $shift,
    ): array {
        $start =
            $shift
            ->opened_at
            ->copy();

        $end =
            $shift->closed_at
            ? $shift
            ->closed_at
            ->copy()
            : now();

        /*
         * =====================================================
         * SALES CREATED DURING REGISTER
         * =====================================================
         */

        $salesQuery =
            Sale::query()
            ->where(
                'created_by',
                $shift->cashier_id,
            )
            ->whereBetween(
                'sale_date',
                [
                    $start,
                    $end,
                ],
            );

        /*
         * =====================================================
         * PAYMENT COLLECTIONS
         * =====================================================
         *
         * SalePayment is the source of truth.
         *
         * Because split payments create separate
         * SalePayment rows:
         *
         * Cash          2,000
         * Card          1,000
         * Bank          1,000
         * Cheque        1,000
         *
         * Cash Register only receives:
         *
         * Rs.2,000
         */
        $paymentsQuery =
            SalePayment::query()
            ->where(
                'created_by',
                $shift->cashier_id,
            )
            ->whereBetween(
                'created_at',
                [
                    $start,
                    $end,
                ],
            );

        /*
         * Physical cash.
         */
        $cashCollections =
            (float) (
                (clone $paymentsQuery)
                ->where(
                    'payment_method',
                    SalePayment::METHOD_CASH,
                )
                ->sum(
                    'amount',
                )
            );

        /*
         * Informational only.
         *
         * Does NOT affect physical drawer cash.
         */
        $cardCollections =
            (float) (
                (clone $paymentsQuery)
                ->where(
                    'payment_method',
                    SalePayment::METHOD_CARD,
                )
                ->sum(
                    'amount',
                )
            );

        /*
         * Informational only.
         *
         * Does NOT affect physical drawer cash.
         */
        $bankCollections =
            (float) (
                (clone $paymentsQuery)
                ->where(
                    'payment_method',
                    SalePayment::METHOD_BANK_TRANSFER,
                )
                ->sum(
                    'amount',
                )
            );

        /*
         * IMPORTANT:
         *
         * Cheque is intentionally NOT added
         * to expected cash.
         *
         * Cheque is not physical cash inside
         * the register drawer.
         */

        /*
         * =====================================================
         * CASH REFUNDS
         * =====================================================
         */

        $cashRefunds =
            (float) SaleReturn::query()
                ->where(
                    'created_by',
                    $shift->cashier_id,
                )
                ->where(
                    'refund_method',
                    SalePayment::METHOD_CASH,
                )
                ->whereBetween(
                    'return_date',
                    [
                        $start,
                        $end,
                    ],
                )
                ->sum(
                    'refund_amount',
                );

        /*
         * =====================================================
         * MANUAL / AUTOMATIC REGISTER MOVEMENTS
         * =====================================================
         *
         * Examples of Cash In:
         *
         * Cash float
         * Extra drawer cash
         *
         * Examples of Cash Out:
         *
         * Petty cash
         * Expense
         * Supplier cash payment
         */

        $movementQuery =
            CashRegisterMovement::query()
            ->where(
                'cashier_shift_id',
                $shift->id,
            );

        $cashIn =
            (float) (
                (clone $movementQuery)
                ->where(
                    'movement_type',
                    CashRegisterMovement::TYPE_CASH_IN,
                )
                ->sum(
                    'amount',
                )
            );

        $cashOut =
            (float) (
                (clone $movementQuery)
                ->where(
                    'movement_type',
                    CashRegisterMovement::TYPE_CASH_OUT,
                )
                ->sum(
                    'amount',
                )
            );

        $openingCash =
            (float) $shift
                ->opening_cash;

        /*
         * =====================================================
         * EXPECTED PHYSICAL CASH
         * =====================================================
         *
         * Expected Cash =
         *
         * Opening Cash
         * + Cash Collections
         * + Cash In
         * - Cash Refunds
         * - Cash Out
         *
         * Supplier Cash Payments are inserted
         * as cash_out movements by the
         * CashRegisterService.
         *
         * Card / Bank / Cheque do NOT affect
         * expected physical cash.
         */

        $expectedCash =
            round(
                $openingCash
                    + $cashCollections
                    + $cashIn
                    - $cashRefunds
                    - $cashOut,
                2,
            );

        return [
            /*
             * Sales.
             */
            'sales_count' => (clone $salesQuery)
                ->count(),

            'sales_total' =>
            (float) (
                (clone $salesQuery)
                ->sum(
                    'grand_total',
                )
            ),

            'due_created' =>
            (float) (
                (clone $salesQuery)
                ->sum(
                    'due_amount',
                )
            ),

            /*
             * Payment methods.
             */
            'cash_collections' =>
            round(
                $cashCollections,
                2,
            ),

            'card_collections' =>
            round(
                $cardCollections,
                2,
            ),

            'bank_transfer_collections' =>
            round(
                $bankCollections,
                2,
            ),

            /*
             * Refunds.
             */
            'cash_refunds' =>
            round(
                $cashRefunds,
                2,
            ),

            /*
             * Register movements.
             */
            'cash_in' =>
            round(
                $cashIn,
                2,
            ),

            'cash_out' =>
            round(
                $cashOut,
                2,
            ),

            /*
             * Final expected drawer cash.
             */
            'expected_cash' =>
            $expectedCash,
        ];
    }

    /*
     * =========================================================
     * CASH REGISTER RESPONSE
     * =========================================================
     */

    /**
     * @return array<string, mixed>
     */
    private function shiftData(
        CashierShift $shift,
        bool $includeMovements,
    ): array {
        $shift->loadMissing([
            'cashier:id,name,username,email,phone',

            'openedBy:id,name,username',

            'closedBy:id,name,username',
        ]);

        $totals =
            $this->calculateTotals(
                $shift,
            );

        $end =
            $shift->closed_at
            ?? now();

        $durationMinutes =
            (int) $shift
                ->opened_at
                ->diffInMinutes(
                    $end,
                );

        /*
         * Load register movement history only
         * when requested.
         */
        $movements =
            $includeMovements
            ? CashRegisterMovement::query()
            ->where(
                'cashier_shift_id',
                $shift->id,
            )
            ->with(
                'createdBy:id,name,username',
            )
            ->latest(
                'occurred_at',
            )
            ->latest(
                'id',
            )
            ->get()
            ->map(
                fn(
                    CashRegisterMovement $movement,
                ): array =>
                $this->movementData(
                    $movement,
                ),
            )
            ->values()

            : collect();

        return [
            'id' =>
            $shift->id,

            'shift_number' =>
            $shift
                ->shift_number,

            'status' =>
            $shift
                ->status,

            'opening_cash' =>
            (float) $shift
                ->opening_cash,

            'opened_at' =>
            $shift
                ->opened_at
                ->toISOString(),

            'closed_at' =>
            $shift
                ->closed_at
                ?->toISOString(),

            /*
             * Open:
             * live expected cash.
             *
             * Closed:
             * final stored expected cash.
             */
            'expected_cash' =>
            $shift->status
                === CashierShift::STATUS_CLOSED
                ? (float) $shift
                    ->expected_cash
                : (float) $totals['expected_cash'],

            'actual_cash' =>
            $shift->actual_cash
                !== null
                ? (float) $shift
                    ->actual_cash
                : null,

            'cash_difference' =>
            $shift->cash_difference
                !== null
                ? (float) $shift
                    ->cash_difference
                : null,

            'opening_notes' =>
            $shift
                ->opening_notes,

            'closing_notes' =>
            $shift
                ->closing_notes,

            'duration_minutes' =>
            $durationMinutes,

            /*
             * Existing database relationship
             * remains named cashier.
             *
             * For an Admin register this relation
             * simply contains the Admin user.
             */
            'cashier' => [
                'id' =>
                $shift
                    ->cashier
                    ->id,

                'name' =>
                $shift
                    ->cashier
                    ->name,

                'username' =>
                $shift
                    ->cashier
                    ->username,

                'email' =>
                $shift
                    ->cashier
                    ->email,

                'phone' =>
                $shift
                    ->cashier
                    ->phone,
            ],

            'opened_by' => [
                'id' =>
                $shift
                    ->openedBy
                    ->id,

                'name' =>
                $shift
                    ->openedBy
                    ->name,
            ],

            'closed_by' =>
            $shift->closedBy
                ? [
                    'id' =>
                    $shift
                        ->closedBy
                        ->id,

                    'name' =>
                    $shift
                        ->closedBy
                        ->name,
                ]
                : null,

            'totals' =>
            $totals,

            'movements' =>
            $movements,
        ];
    }

    /*
     * =========================================================
     * MOVEMENT RESPONSE
     * =========================================================
     */

    /**
     * @return array<string, mixed>
     */
    private function movementData(
        CashRegisterMovement $movement,
    ): array {
        $movement->loadMissing(
            'createdBy:id,name,username',
        );

        return [
            'id' =>
            $movement->id,

            'movement_type' =>
            $movement
                ->movement_type,

            'reason' =>
            $movement
                ->reason,

            'amount' =>
            (float) $movement
                ->amount,

            'description' =>
            $movement
                ->description,

            'reference_number' =>
            $movement
                ->reference_number,

            'occurred_at' =>
            $movement
                ->occurred_at
                ->toISOString(),

            'created_by' => [
                'id' =>
                $movement
                    ->createdBy
                    ->id,

                'name' =>
                $movement
                    ->createdBy
                    ->name,
            ],
        ];
    }

    /*
     * =========================================================
     * ROLE CHECK
     * =========================================================
     */

    private function canUseCashRegister(
        User $user,
    ): bool {
        return $user->isAdmin()
            || $user->isCashier();
    }
}
