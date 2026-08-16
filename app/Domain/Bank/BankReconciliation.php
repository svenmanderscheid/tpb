<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Expense\ExpenseRepo;
use Tpb\Domain\Payment\PaymentService;

/**
 * Bestätigter Bankabgleich (§5.6). NIE automatisch – die Buchung entsteht erst durch die
 * Bestätigung von finance/owner: in EINER Transaktion Zahlung + Zuordnung (Eingang) bzw.
 * Ausgabe (Ausgang), Ableitung der Zahlungsachse und Setzen von match_status='confirmed'.
 */
final class BankReconciliation
{
    /** Eingangszeile ⇒ Zahlung auf eine Rechnung buchen + zuordnen. */
    public static function confirmPayment(int $bankLineId, int $invoiceId, ?int $actorUserId): void
    {
        Db::tx(function () use ($bankLineId, $invoiceId, $actorUserId): void {
            $line = Db::run('SELECT * FROM bank_lines WHERE id = ? FOR UPDATE', [$bankLineId])->fetch();
            if ($line === false || !in_array((string) $line['match_status'], ['open', 'suggested'], true)) {
                throw new BankException('Bankzeile ist nicht (mehr) offen.');
            }
            if ((int) $line['amount_cents'] <= 0) {
                throw new BankException('Für Ausgangszeilen bitte eine Ausgabe erfassen.');
            }
            $invoice = Db::run('SELECT id, order_id, gross_cents FROM invoices WHERE id = ? LIMIT 1', [$invoiceId])->fetch();
            if ($invoice === false || $invoice['order_id'] === null) {
                throw new BankException('Rechnung/Auftrag nicht gefunden.');
            }
            $orderId = (int) $invoice['order_id'];
            $now = Clock::nowUtcSeconds();

            Db::run(
                'INSERT INTO payments (order_id, method, amount_cents, currency, received_at, reference, recorded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, 'bank_transfer', (int) $line['amount_cents'], (string) $line['currency'], (string) $line['booking_date'],
                 mb_substr((string) ($line['remittance_info'] ?? $line['end_to_end_id'] ?? ''), 0, 140) ?: null, $actorUserId, $now]
            );
            $paymentId = (int) Db::pdo()->lastInsertId();

            $open = (int) $invoice['gross_cents'] - (int) Db::run('SELECT COALESCE(SUM(amount_cents),0) FROM payment_allocations WHERE invoice_id = ?', [$invoiceId])->fetchColumn();
            $alloc = max(0, min((int) $line['amount_cents'], $open));
            if ($alloc > 0) {
                Db::run('INSERT INTO payment_allocations (payment_id, invoice_id, amount_cents, created_at) VALUES (?, ?, ?, ?)', [$paymentId, $invoiceId, $alloc, $now]);
            }
            PaymentService::deriveForOrder($orderId, $actorUserId);

            Db::run("UPDATE bank_lines SET match_status = 'confirmed', matched_payment_id = ?, confirmed_by = ?, confirmed_at = ? WHERE id = ?", [$paymentId, $actorUserId, $now, $bankLineId]);

            Audit::log('bank_line', (string) $bankLineId, 'bank.payment_confirmed', [
                'actor_user_id' => $actorUserId,
                'metadata'      => ['payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'amount' => (int) $line['amount_cents']],
            ]);
        });
    }

    /** Ausgangszeile ⇒ Ausgabe erfassen. */
    public static function confirmExpense(int $bankLineId, string $category, ?int $actorUserId): void
    {
        Db::tx(function () use ($bankLineId, $category, $actorUserId): void {
            $line = Db::run('SELECT * FROM bank_lines WHERE id = ? FOR UPDATE', [$bankLineId])->fetch();
            if ($line === false || !in_array((string) $line['match_status'], ['open', 'suggested'], true)) {
                throw new BankException('Bankzeile ist nicht (mehr) offen.');
            }
            if ((int) $line['amount_cents'] >= 0) {
                throw new BankException('Für Eingangszeilen bitte eine Zahlung zuordnen.');
            }
            $expense = ExpenseRepo::create([
                'expense_date' => (string) $line['booking_date'],
                'vendor'       => (string) ($line['counterparty_name'] ?? 'Unbekannt'),
                'category'     => $category !== '' ? $category : 'Bank',
                'description'  => (string) ($line['remittance_info'] ?? ''),
                'amount_cents' => abs((int) $line['amount_cents']),
                'bank_line_id' => $bankLineId,
            ], $actorUserId);

            Db::run("UPDATE bank_lines SET match_status = 'confirmed', matched_expense_id = ?, confirmed_by = ?, confirmed_at = ? WHERE id = ?",
                [$expense['id'], $actorUserId, Clock::nowUtcSeconds(), $bankLineId]);

            Audit::log('bank_line', (string) $bankLineId, 'bank.expense_confirmed', ['actor_user_id' => $actorUserId, 'metadata' => ['expense_id' => $expense['id']]]);
        });
    }

    public static function ignore(int $bankLineId, ?int $actorUserId): void
    {
        Db::run("UPDATE bank_lines SET match_status = 'ignored', confirmed_by = ?, confirmed_at = ? WHERE id = ? AND match_status IN ('open','suggested')",
            [$actorUserId, Clock::nowUtcSeconds(), $bankLineId]);
        Audit::log('bank_line', (string) $bankLineId, 'bank.ignored', ['actor_user_id' => $actorUserId]);
    }
}
