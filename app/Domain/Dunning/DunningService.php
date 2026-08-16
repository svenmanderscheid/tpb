<?php
declare(strict_types=1);

namespace Tpb\Domain\Dunning;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Outbox\MailTemplates;
use Tpb\Domain\Outbox\Outbox;

/**
 * Mahnwesen (Erinnerung + Stufe 1) und Wiedervorlage ablaufender Angebote (§5.6/§12).
 * Jede Stufe wird über reminders_sent (UNIQUE ref_type,ref_id,reminder_type,level) genau
 * einmal versandt – ein mehrfacher Lauf erzeugt keine Dubletten.
 *
 * @return array{payment_reminders:int,quote_followups:int}
 */
final class DunningService
{
    private const FOLLOWUP_LOOKAHEAD_DAYS = 3;

    /** @return array{payment_reminders:int,quote_followups:int} */
    public static function run(): array
    {
        $today = Clock::nowUtc()->format('Y-m-d');
        return [
            'payment_reminders' => self::overdueInvoiceReminders($today),
            'quote_followups'   => self::expiringQuoteFollowups($today),
        ];
    }

    private static function overdueInvoiceReminders(string $today): int
    {
        $sent = 0;
        $rows = Db::run(
            "SELECT i.id, i.invoice_number, i.gross_cents, i.currency, i.due_date, o.customer_snapshot_json
             FROM invoices i JOIN orders o ON o.id = i.order_id
             WHERE i.status IN ('ISSUED','SENT') AND i.doc_type = 'invoice' AND i.due_date IS NOT NULL AND i.due_date < ?",
            [$today]
        )->fetchAll();

        foreach ($rows as $inv) {
            $paid = (int) Db::run('SELECT COALESCE(SUM(amount_cents),0) FROM payment_allocations WHERE invoice_id = ?', [(int) $inv['id']])->fetchColumn();
            if ($paid >= (int) $inv['gross_cents']) {
                continue; // vollständig bezahlt
            }
            if (!self::claim('invoice', (int) $inv['id'], 'payment', 1)) {
                continue; // Stufe bereits versandt
            }
            $c = json_decode((string) $inv['customer_snapshot_json'], true) ?: [];
            if (empty($c['email'])) {
                continue;
            }
            $mail = [
                'invoice_number' => (string) $inv['invoice_number'],
                'to_email'       => (string) $c['email'],
                'to_name'        => trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? '')),
                'gross_cents'    => (int) $inv['gross_cents'],
                'currency'       => (string) $inv['currency'],
                'due_date'       => (string) $inv['due_date'],
            ];
            $oid = Outbox::enqueue('mail.payment_reminder', array_merge($mail, MailTemplates::paymentReminder($mail)), 'payment_reminder_' . (int) $inv['id'] . '_1');
            Db::run("UPDATE reminders_sent SET outbox_id = ? WHERE ref_type='invoice' AND ref_id=? AND reminder_type='payment' AND level=1", [$oid, (int) $inv['id']]);
            $sent++;
        }
        return $sent;
    }

    private static function expiringQuoteFollowups(string $today): int
    {
        $until = Clock::nowUtc()->modify('+' . self::FOLLOWUP_LOOKAHEAD_DAYS . ' days')->format('Y-m-d');
        $sent = 0;
        $rows = Db::run(
            "SELECT id, quote_number, valid_until FROM quotes WHERE status = 'SENT' AND valid_until IS NOT NULL AND valid_until >= ? AND valid_until <= ?",
            [$today, $until]
        )->fetchAll();

        $owner = (string) (Db::run("SELECT email FROM users WHERE role='owner' AND status='active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: '');
        if ($owner === '') {
            return 0;
        }
        foreach ($rows as $q) {
            if (!self::claim('quote', (int) $q['id'], 'expiry', 1)) {
                continue;
            }
            $subject = 'Wiedervorlage: Angebot ' . (string) $q['quote_number'] . ' läuft am ' . (string) $q['valid_until'] . ' ab';
            $oid = Outbox::enqueue('mail.quote_followup', [
                'to'      => $owner,
                'subject' => $subject,
                'text'    => "Das Angebot {$q['quote_number']} läuft am {$q['valid_until']} ab und ist noch offen.\nBitte ggf. beim Kunden nachfassen.",
            ], 'quote_followup_' . (int) $q['id'] . '_1');
            Db::run("UPDATE reminders_sent SET outbox_id = ? WHERE ref_type='quote' AND ref_id=? AND reminder_type='expiry' AND level=1", [$oid, (int) $q['id']]);
            $sent++;
        }
        return $sent;
    }

    /** Reserviert eine Mahn-/Wiedervorlage-Stufe. true = neu (versenden), false = schon versandt. */
    private static function claim(string $refType, int $refId, string $type, int $level): bool
    {
        $stmt = Db::run(
            'INSERT IGNORE INTO reminders_sent (ref_type, ref_id, reminder_type, level, sent_at) VALUES (?, ?, ?, ?, ?)',
            [$refType, $refId, $type, $level, Clock::nowUtcSeconds()]
        );
        return $stmt->rowCount() === 1;
    }
}
