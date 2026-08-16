<?php
declare(strict_types=1);

namespace Tpb\Domain\Report;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * „Heute"-Liste fürs Dashboard (§12, M8): ablaufende Angebote, wartende Proofs,
 * überfällige Rechnungen, offene Bankzeilen, blockierte Jobs. Nur Zahlen + kurze Listen.
 */
final class TodayList
{
    /** @return array<string,mixed> */
    public static function gather(): array
    {
        $today = Clock::nowUtc()->format('Y-m-d');
        $soon = Clock::nowUtc()->modify('+3 days')->format('Y-m-d');

        $expiringQuotes = Db::run(
            "SELECT q.public_id, q.quote_number, q.valid_until, c.company_name, c.first_name, c.last_name
             FROM quotes q JOIN customers c ON c.id = q.customer_id
             WHERE q.status = 'SENT' AND q.valid_until IS NOT NULL AND q.valid_until >= ? AND q.valid_until <= ?
             ORDER BY q.valid_until ASC LIMIT 20",
            [$today, $soon]
        )->fetchAll();

        $overdueInvoices = Db::run(
            "SELECT i.public_id, i.invoice_number, i.gross_cents, i.currency, i.due_date
             FROM invoices i
             WHERE i.status IN ('ISSUED','SENT') AND i.doc_type = 'invoice' AND i.due_date IS NOT NULL AND i.due_date < ?
               AND i.gross_cents > (SELECT COALESCE(SUM(amount_cents),0) FROM payment_allocations pa WHERE pa.invoice_id = i.id)
             ORDER BY i.due_date ASC LIMIT 20",
            [$today]
        )->fetchAll();

        $waitingProofs = (int) Db::run("SELECT COUNT(*) FROM proofs WHERE status = 'sent'")->fetchColumn();
        $openBankLines = (int) Db::run("SELECT COUNT(*) FROM bank_lines WHERE match_status IN ('open','suggested')")->fetchColumn();
        $blockedJobs = (int) Db::run("SELECT COUNT(*) FROM production_jobs WHERE status = 'BLOCKED'")->fetchColumn();

        return [
            'expiring_quotes'  => $expiringQuotes,
            'overdue_invoices' => $overdueInvoices,
            'waiting_proofs'   => $waitingProofs,
            'open_bank_lines'  => $openBankLines,
            'blocked_jobs'     => $blockedJobs,
        ];
    }
}
