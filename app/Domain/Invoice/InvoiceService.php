<?php
declare(strict_types=1);

namespace Tpb\Domain\Invoice;

use Tpb\Core\Canonical;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\Outbox\MailTemplates;
use Tpb\Domain\Outbox\Outbox;
use Tpb\Domain\Pdf\PdfService;
use Tpb\Domain\Sequence\NumberSequence;
use Tpb\Domain\Status\Status;

/**
 * Rechnungen & Gutschriften (§11.2 Issue-Flow). Draft aus Auftrag → Ausstellung
 * (Nummer ziehen, Seller/Customer/Tax/Lines einfrieren, kanonisches JSON, PDF aus
 * DEMSELBEN Snapshot, beide als Assets + SHA-256, Status ISSUED = unumkehrbar).
 * Steuer: aktives Regime aus tax_regime_versions (Franchise Art. 57bis → 0 % USt,
 * DECISIONS #29), nie hart codiert.
 */
final class InvoiceService
{
    /** Erstellt (oder liefert) den Rechnungs-Draft eines Auftrags. @return array{id:int,public_id:string} */
    public static function createDraft(int $orderId, ?int $actorUserId): array
    {
        return Db::tx(function () use ($orderId, $actorUserId): array {
            $existing = InvoiceRepo::primaryForOrder($orderId);
            if ($existing !== null) {
                return ['id' => (int) $existing['id'], 'public_id' => (string) $existing['public_id']];
            }
            $order = Db::run('SELECT * FROM orders WHERE id = ? LIMIT 1', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }

            $lines = self::draftLinesFromOrder($orderId);
            $net = array_sum(array_map(static fn ($l) => (int) $l['line_cents'], $lines));
            $tax = 0; // Franchise Art. 57bis: keine USt (DECISIONS #29)
            $gross = $net + $tax;

            $publicId = Ulid::generate();
            $now = Clock::nowUtcSeconds();
            Db::run(
                'INSERT INTO invoices (public_id, doc_type, status, order_id, currency, net_cents, tax_cents, gross_cents, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$publicId, 'invoice', 'DRAFT', $orderId, (string) $order['currency'], $net, $tax, $gross, $actorUserId, $now, $now]
            );
            $invoiceId = (int) Db::pdo()->lastInsertId();
            self::insertLines($invoiceId, $lines);

            Status::transition('invoice', $invoiceId, 'invoice', null, 'DRAFT', ['actor_user_id' => $actorUserId]);
            Audit::log('invoice', $publicId, 'invoice.draft_created', ['actor_user_id' => $actorUserId, 'metadata' => ['order_id' => $orderId, 'gross' => $gross]]);

            return ['id' => $invoiceId, 'public_id' => $publicId];
        });
    }

    /**
     * Issue-Flow (§11.2): friert Snapshot ein, zieht die Nummer, rendert PDF + JSON aus
     * DEMSELBEN Snapshot, setzt ISSUED (unumkehrbar). @return array{invoice_number:string}
     */
    public static function issue(int $invoiceId, ?int $actorUserId): array
    {
        return Db::tx(function () use ($invoiceId, $actorUserId): array {
            $inv = Db::run('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId])->fetch();
            if ($inv === false) {
                throw new \InvalidArgumentException('Rechnung nicht gefunden.');
            }
            if ((string) $inv['status'] !== 'DRAFT') {
                throw new InvoiceStateException('Nur Entwürfe können ausgestellt werden (Status: ' . (string) $inv['status'] . ').');
            }

            $order = $inv['order_id'] !== null ? Db::run('SELECT * FROM orders WHERE id = ? LIMIT 1', [(int) $inv['order_id']])->fetch() : false;
            $lines = InvoiceRepo::lines($invoiceId);
            [$taxCode, $taxLegend] = self::activeTaxRegime();
            $seller = self::sellerSnapshot();
            $customer = $order !== false ? (json_decode((string) $order['customer_snapshot_json'], true) ?: []) : [];

            $number = NumberSequence::nextInvoice();
            $issuedAt = Clock::nowUtc()->format('Y-m-d');
            $dueDate = Clock::nowUtc()->modify('+' . self::dueDays() . ' days')->format('Y-m-d');

            $credited = null;
            if ($inv['credited_invoice_id'] !== null) {
                $credited = Db::run('SELECT invoice_number FROM invoices WHERE id = ? LIMIT 1', [(int) $inv['credited_invoice_id']])->fetchColumn() ?: null;
            }

            // Kanonischer Snapshot – Quelle für JSON UND PDF.
            $snapshot = [
                'doc_type'        => (string) $inv['doc_type'],
                'invoice_number'  => $number,
                'credited_number' => $credited,
                'issued_at'       => $issuedAt,
                'due_date'        => $dueDate,
                'currency'        => (string) $inv['currency'],
                'seller'          => $seller,
                'customer'        => $customer,
                'tax'             => ['code' => $taxCode, 'legend' => $taxLegend],
                'lines'           => array_map(static fn ($l) => [
                    'pos_no' => (int) $l['pos_no'], 'sku' => $l['sku'], 'description' => (string) $l['description'],
                    'qty' => (int) $l['qty'], 'unit_cents' => (int) $l['unit_cents'], 'line_cents' => (int) $l['line_cents'],
                ], $lines),
                'totals'          => ['net_cents' => (int) $inv['net_cents'], 'tax_cents' => (int) $inv['tax_cents'], 'gross_cents' => (int) $inv['gross_cents']],
            ];
            $json = Canonical::json($snapshot);
            $sha = hash('sha256', $json);

            $pdf = PdfService::render('invoice', ['snapshot' => $snapshot]);

            $jsonAsset = AssetService::storeGenerated($json, 'Rechnung_' . $number . '.json', 'application/json', 'invoice_json', 'invoice_10y', $actorUserId, 'invoice', $invoiceId);
            $pdfAsset = AssetService::storeGenerated($pdf, 'Rechnung_' . $number . '.pdf', 'application/pdf', 'invoice_pdf', 'invoice_10y', $actorUserId, 'invoice', $invoiceId);

            Db::run(
                "UPDATE invoices SET invoice_number = ?, status = 'ISSUED', issued_at = ?, due_date = ?,
                    seller_snapshot_json = ?, customer_snapshot_json = ?, tax_regime_code = ?, tax_legend = ?,
                    snapshot_json = ?, snapshot_sha256 = ?, pdf_asset_id = ?, json_asset_id = ?, updated_at = ?
                 WHERE id = ?",
                [
                    $number, $issuedAt, $dueDate, Canonical::json($seller), Canonical::json($customer), $taxCode, $taxLegend,
                    $json, $sha, $pdfAsset['id'], $jsonAsset['id'], Clock::nowUtcSeconds(), $invoiceId,
                ]
            );
            Status::transition('invoice', $invoiceId, 'invoice', 'DRAFT', 'ISSUED', ['actor_user_id' => $actorUserId]);

            // Zahlungsachse neu ableiten: nun besteht eine Forderung (Umsatz), Eingang 0 ⇒ UNPAID.
            if ($inv['order_id'] !== null) {
                \Tpb\Domain\Payment\PaymentService::deriveForOrder((int) $inv['order_id'], $actorUserId);
            }

            // Bei Vollzahlungs-Kontext später; hier Versand als Outbox-Event.
            if (!empty($customer['email'])) {
                $mail = [
                    'invoice_number' => $number,
                    'to_email'       => $customer['email'],
                    'to_name'        => trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? '')),
                    'gross_cents'    => (int) $inv['gross_cents'],
                    'currency'       => (string) $inv['currency'],
                    'doc_type'       => (string) $inv['doc_type'],
                ];
                Outbox::enqueue('mail.invoice_issued', array_merge($mail, MailTemplates::invoiceIssued($mail)), 'invoice_issued_' . $invoiceId);
            }

            Audit::log('invoice', (string) $inv['public_id'], 'invoice.issued', [
                'actor_user_id' => $actorUserId, 'from_state' => 'DRAFT', 'to_state' => 'ISSUED',
                'metadata' => ['invoice_number' => $number, 'sha256' => $sha, 'gross' => (int) $inv['gross_cents']],
            ]);

            return ['invoice_number' => $number];
        });
    }

    /**
     * Vollständige Gutschrift zu einer ausgestellten Rechnung (§7: ISSUED → FULLY_CREDITED).
     * Erzeugt einen Gutschrift-Beleg (negierte Positionen), stellt ihn aus und markiert das
     * Original als FULLY_CREDITED. @return array{credit_public_id:string,invoice_number:string}
     */
    public static function creditNote(int $originalInvoiceId, ?int $actorUserId): array
    {
        $creditId = Db::tx(function () use ($originalInvoiceId, $actorUserId): int {
            $orig = Db::run('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$originalInvoiceId])->fetch();
            if ($orig === false) {
                throw new \InvalidArgumentException('Rechnung nicht gefunden.');
            }
            if ((string) $orig['status'] !== 'ISSUED' && (string) $orig['status'] !== 'SENT') {
                throw new InvoiceStateException('Nur ausgestellte Rechnungen können gutgeschrieben werden.');
            }
            if ((string) $orig['doc_type'] !== 'invoice') {
                throw new InvoiceStateException('Zu einer Gutschrift kann keine Gutschrift erstellt werden.');
            }

            $publicId = Ulid::generate();
            $now = Clock::nowUtcSeconds();
            Db::run(
                'INSERT INTO invoices (public_id, doc_type, status, order_id, credited_invoice_id, currency, net_cents, tax_cents, gross_cents, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$publicId, 'credit_note', 'DRAFT', $orig['order_id'], $originalInvoiceId, (string) $orig['currency'],
                 -(int) $orig['net_cents'], -(int) $orig['tax_cents'], -(int) $orig['gross_cents'], $actorUserId, $now, $now]
            );
            $creditId = (int) Db::pdo()->lastInsertId();

            $pos = 0;
            foreach (InvoiceRepo::lines($originalInvoiceId) as $l) {
                $pos++;
                Db::run(
                    'INSERT INTO invoice_lines (invoice_id, pos_no, sku, description, qty, unit_cents, line_cents, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$creditId, $pos, $l['sku'], 'Gutschrift: ' . (string) $l['description'], (int) $l['qty'], -(int) $l['unit_cents'], -(int) $l['line_cents'], $now]
                );
            }
            Status::transition('invoice', $creditId, 'invoice', null, 'DRAFT', ['actor_user_id' => $actorUserId]);
            return $creditId;
        });

        $issued = self::issue($creditId, $actorUserId);

        Db::tx(function () use ($originalInvoiceId, $actorUserId): void {
            $orig = Db::run('SELECT status FROM invoices WHERE id = ? FOR UPDATE', [$originalInvoiceId])->fetch();
            $from = (string) $orig['status'];
            Db::run("UPDATE invoices SET status = 'FULLY_CREDITED', updated_at = ? WHERE id = ?", [Clock::nowUtcSeconds(), $originalInvoiceId]);
            Status::transition('invoice', $originalInvoiceId, 'invoice', $from, 'FULLY_CREDITED', ['actor_user_id' => $actorUserId]);
            Audit::log('invoice', (string) $originalInvoiceId, 'invoice.fully_credited', ['actor_user_id' => $actorUserId, 'to_state' => 'FULLY_CREDITED']);
        });

        $credit = InvoiceRepo::findById($creditId);
        return ['credit_public_id' => (string) $credit['public_id'], 'invoice_number' => $issued['invoice_number']];
    }

    /** @return array<int,array<string,mixed>> */
    private static function draftLinesFromOrder(int $orderId): array
    {
        $lines = [];
        $pos = 0;
        foreach (Db::run('SELECT sku, description, qty, unit_cents, line_cents FROM order_items WHERE order_id = ? ORDER BY pos_no ASC', [$orderId])->fetchAll() as $it) {
            $pos++;
            $lines[] = ['pos_no' => $pos, 'sku' => $it['sku'], 'description' => (string) $it['description'], 'qty' => (int) $it['qty'], 'unit_cents' => (int) $it['unit_cents'], 'line_cents' => (int) $it['line_cents']];
        }
        $shipment = Db::run('SELECT shipping_cost_cents FROM shipments WHERE order_id = ? LIMIT 1', [$orderId])->fetch();
        if ($shipment !== false && (int) $shipment['shipping_cost_cents'] > 0) {
            $pos++;
            $lines[] = ['pos_no' => $pos, 'sku' => null, 'description' => 'Versandkosten', 'qty' => 1, 'unit_cents' => (int) $shipment['shipping_cost_cents'], 'line_cents' => (int) $shipment['shipping_cost_cents']];
        }
        return $lines;
    }

    /** @param array<int,array<string,mixed>> $lines */
    private static function insertLines(int $invoiceId, array $lines): void
    {
        $now = Clock::nowUtcSeconds();
        foreach ($lines as $l) {
            Db::run(
                'INSERT INTO invoice_lines (invoice_id, pos_no, sku, description, qty, unit_cents, line_cents, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceId, $l['pos_no'], $l['sku'], $l['description'], $l['qty'], $l['unit_cents'], $l['line_cents'], $now]
            );
        }
    }

    /** @return array{0:string,1:string} [regime_code, legend] */
    private static function activeTaxRegime(): array
    {
        $row = Db::run("SELECT value_json FROM business_settings WHERE setting_key = 'tax.active_regime' LIMIT 1")->fetch();
        $code = $row !== false ? (string) json_decode((string) $row['value_json'], true) : '';
        if ($code === '') {
            return ['', ''];
        }
        $reg = Db::run(
            'SELECT legend_text FROM tax_regime_versions WHERE regime_code = ? ORDER BY id DESC LIMIT 1',
            [$code]
        )->fetch();
        return [$code, $reg !== false ? (string) $reg['legend_text'] : ''];
    }

    /** @return array<string,mixed> */
    private static function sellerSnapshot(): array
    {
        $row = Db::run("SELECT value_json FROM business_settings WHERE setting_key = 'seller.snapshot' LIMIT 1")->fetch();
        $s = $row !== false ? json_decode((string) $row['value_json'], true) : null;
        return is_array($s) ? $s : ['placeholder' => true, 'name' => 'The Printing Brothers (PLATZHALTER)'];
    }

    private static function dueDays(): int
    {
        $row = Db::run("SELECT value_json FROM business_settings WHERE setting_key = 'invoice.due_days' LIMIT 1")->fetch();
        $v = $row !== false ? (int) json_decode((string) $row['value_json'], true) : 30;
        return $v > 0 ? $v : 30;
    }
}
