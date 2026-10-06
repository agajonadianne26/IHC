-- 011_backfill_payment_allocations_ihc.sql
-- Backfill payment_allocations for legacy payments that predate the
-- allocation-aware ledger. Each legacy payment is treated as covering
-- contract principal in full (the historical fallback behavior), one
-- allocation row per payment whose OR series prefix tells us DP vs INS.
-- Idempotent: skips payments that already have an allocation row.

INSERT INTO payment_allocations (payment_id, contract_id, installment_kind, installment_no, allocated_amount, principal_amount, interest_amount, penalty_amount)
SELECT p.id,
       CAST(REGEXP_REPLACE(p.contract_id, '^[^0-9]*', '') AS UNSIGNED),
       COALESCE(NULLIF(p.installment_kind, ''), IF(p.or_number LIKE 'OR-DP-%', 'downpayment', 'installment')),
       p.installment_no,
       p.amount,
       p.amount,
       0,
       0
FROM payments p
LEFT JOIN payment_allocations a ON a.payment_id = p.id
WHERE a.id IS NULL;
