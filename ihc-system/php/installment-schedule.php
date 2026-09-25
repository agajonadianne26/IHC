<?php
declare(strict_types=1);

/**
 * Installment schedule math shared by the three dashboard endpoints:
 *
 *   php/api_dashboard.php  -> Clerk Dashboard  (next payment + posting queues)
 *   php/api_admin.php      -> Admin Dashboard  (ledger + per-contract schedule)
 *   php/api_client.php     -> Client Dashboard (its Installment Schedules table)
 *
 * All three read the same `payments` rows, and this file is the single place
 * that decides how those rows map onto a contract's installments — so the
 * three dashboards can never disagree about what is paid or what is due next.
 *
 * Pure functions only: no PDO, no output, no shared state. `require_once`
 * this file and pass contract/payment rows in.
 */

/** Month arithmetic shared with the dashboards: clamp the day to the last day
 *  of the target month (start_date 2026-01-31 + 1 month -> 2026-02-28). */
function ihc_add_months(string $date, int $months): string {
    [$y, $mo, $d] = array_map('intval', explode('-', $date));
    $mo += $months;
    $y += intdiv($mo - 1, 12);
    $mo = (($mo - 1) % 12 + 12) % 12 + 1;
    $lastDay = (int)date('t', mktime(0, 0, 0, $mo, 1, $y));
    return sprintf('%04d-%02d-%02d', $y, $mo, min($d, $lastDay));
}

/** Principal cash applied to the contract schedule. New payments carry a
 * principalAmount from payment_allocations; legacy rows without allocations
 * fall back to the full payment amount for backward compatibility. */
function ihc_schedule_payment_amount(array $payment): float {
    if (array_key_exists('principalAmount', $payment) && $payment['principalAmount'] !== null) {
        return max(0.0, (float)$payment['principalAmount']);
    }
    return max(0.0, (float)($payment['amount'] ?? 0));
}

/** Status of an unpaid installment from its due date. */
function ihc_due_status(string $dueDate, string $today): string {
    if ($dueDate === '') return 'current';
    if ($dueDate < $today) return 'overdue';
    $days = (int)floor((strtotime($dueDate) - strtotime($today)) / 86400);
    return $days <= 3 ? 'due-soon' : 'current';
}

/**
 * Raw installment definitions for a `contracts` row:
 *   installment 1 = the downpayment at start_date,
 *   then one amortization per term month; the last absorbs the rounding
 *   remainder so the schedule still totals the contract price exactly.
 *
 * Returns [ ['kind'=>'downpayment'|'installment', 'no'=>null|int, 'dueDate'=>'Y-m-d', 'amount'=>float], ... ]
 */
function ihc_build_installments(array $contract): array {
    $grossTcp = (float)($contract['total_contract_price'] ?? 0);
    $discount = min($grossTcp, max(0.0, (float)($contract['discount_amount'] ?? 0)));
    $tcp      = max(0.0, $grossTcp - $discount);
    $dp       = min($tcp, max(0.0, (float)($contract['downpayment'] ?? 0)));
    $terms  = max(1, (int)($contract['installment_terms'] ?? 1));
    $start  = (string)($contract['start_date'] ?? '');
    if ($start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');

    $list = [['kind' => 'downpayment', 'no' => null, 'dueDate' => $start, 'amount' => round($dp, 2)]];
    $amortTotal = round($tcp - $dp, 2);
    $per = floor($amortTotal / $terms * 100) / 100;
    for ($i = 1; $i <= $terms; $i++) {
        $amt = ($i === $terms) ? round($amortTotal - $per * ($terms - 1), 2) : $per;
        $list[] = ['kind' => 'installment', 'no' => $i, 'dueDate' => ihc_add_months($start, $i), 'amount' => $amt];
    }
    return $list;
}

/**
 * Build the schedule and allocate posted payments against it chronologically:
 * the downpayment clears first, then each installment in turn. A partially
 * covered installment keeps its balance as the amount due; the first
 * uncovered installment is the contract's `next` payment; when every
 * installment is covered the contract is `settled`.
 *
 * $contract: a row from `contracts` (total_contract_price, discount_amount,
 *            downpayment, installment_terms, start_date).
 * $payments: chronological rows —
 *            ['amount'=>float, 'principalAmount'=>?float, 'date'=>'Y-m-d', 'orNumber'=>?string, 'method'=>?string]
 * $today:    'Y-m-d'; defaults to the server date.
 *
 * Returns:
 *   'installments' => schedule rows enriched with paid/paidAmount/payDate/
 *                     orNumber/method/status ('paid'|'overdue'|'due-soon'|'current')
 *   'next'         => first uncovered installment (amount = balance still due)
 *                     or null when the contract is fully paid
 *   'paid'         => total posted payments for the contract
 *   'outstanding'  => net contract price (TCP less discount) still uncollected
 *   'settled'      => bool
 */
function ihc_schedule(array $contract, array $payments = [], ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $installments = ihc_build_installments($contract);

    $paidTotal = 0.0;
    foreach ($payments as $p) $paidTotal += ihc_schedule_payment_amount($p);

    // Walk the schedule once, consuming payments in order. $cur/$curLeft hold
    // the payment currently being spent so one payment can span installments
    // and one installment can be completed by a later payment.
    $pi = 0;
    $cur = null;
    $curLeft = 0.0;
    $next = null;
    $count = count($payments);

    foreach ($installments as &$inst) {
        $left  = $inst['amount'];
        $paidAmt = 0.0;
        $touch = null;

        while ($left > 0.009 && $pi < $count) {
            if ($cur === null) {
                $cur = $payments[$pi];
                $curLeft = ihc_schedule_payment_amount($cur);
                if ($curLeft <= 0.009) { $cur = null; $curLeft = 0.0; $pi++; continue; }
            }
            $take = min($curLeft, $left);
            $left     -= $take;
            $curLeft  -= $take;
            $paidAmt  += $take;
            $touch = $cur;
            if ($curLeft <= 0.009) { $cur = null; $curLeft = 0.0; $pi++; }
        }

        $covered = $paidAmt >= $inst['amount'] - 0.009;
        $inst['paidAmount'] = round($paidAmt, 2);
        $inst['paid']       = $covered;
        // The payment that finished the installment carries its receipt details.
        $inst['payDate']   = $covered && $touch ? ($touch['date'] ?? null) : null;
        $inst['orNumber']  = $covered && $touch ? ($touch['orNumber'] ?? null) : null;
        $inst['method']    = $covered && $touch ? ($touch['method'] ?? null) : null;
        $inst['status']    = $covered ? 'paid' : ihc_due_status((string)$inst['dueDate'], $today);

        if (!$covered && $next === null) {
            $next = [
                'kind'    => $inst['kind'],
                'no'      => $inst['no'],
                'dueDate' => $inst['dueDate'],
                'amount'  => round(max(0.0, $inst['amount'] - $paidAmt), 2),
                'full'    => $inst['amount'],
            ];
        }
    }
    unset($inst);

    $grossTcp = (float)($contract['total_contract_price'] ?? 0);
    $discount = min($grossTcp, max(0.0, (float)($contract['discount_amount'] ?? 0)));
    $tcp = max(0.0, $grossTcp - $discount);

    return [
        'installments' => $installments,
        'next'         => $next,
        'paid'         => round($paidTotal, 2),
        'outstanding'  => max(0.0, round($tcp - $paidTotal, 2)),
        'settled'      => $next === null,
    ];
}
