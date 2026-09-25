<?php
declare(strict_types=1);

/**
 * Create the independent annual OR counters on first use. This keeps the
 * payment endpoint working on older databases before migration 008 is run.
 */
function ihc_ensure_payment_or_counters(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payment_or_counters (
          series_code VARCHAR(3) NOT NULL,
          series_year SMALLINT UNSIGNED NOT NULL,
          last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (series_code, series_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Allocate the next OR number while the caller owns the payment transaction.
 * The atomic upsert locks one counter row, so two concurrent clerks cannot
 * allocate the same sequence. A final existence check also skips any legacy
 * number that may already use the generated format.
 */
function ihc_allocate_payment_or_number(PDO $pdo, string $paymentKind, int $year): string
{
    if (!in_array($paymentKind, ['downpayment', 'installment'], true)) {
        throw new InvalidArgumentException('Invalid payment type for OR allocation.');
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('OR allocation must run inside a payment transaction.');
    }

    $seriesCode = $paymentKind === 'downpayment' ? 'DP' : 'INS';
    $bump = $pdo->prepare(
        'INSERT INTO payment_or_counters (series_code, series_year, last_number)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE last_number = last_number + 1'
    );
    $read = $pdo->prepare(
        'SELECT last_number FROM payment_or_counters
         WHERE series_code = ? AND series_year = ? FOR UPDATE'
    );
    $exists = $pdo->prepare('SELECT 1 FROM payments WHERE or_number = ? LIMIT 1');

    do {
        $bump->execute([$seriesCode, $year]);
        $read->execute([$seriesCode, $year]);
        $sequence = $read->fetchColumn();
        if ($sequence === false) {
            throw new RuntimeException('Could not allocate an OR / Reference number.');
        }

        $orNumber = sprintf('OR-%s-%d-%05d', $seriesCode, $year, (int)$sequence);
        $exists->execute([$orNumber]);
    } while ($exists->fetchColumn() !== false);

    return $orNumber;
}
