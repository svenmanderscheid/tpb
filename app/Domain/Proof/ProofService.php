<?php
declare(strict_types=1);

namespace Tpb\Domain\Proof;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Outbox\MailTemplates;
use Tpb\Domain\Outbox\Outbox;
use Tpb\Domain\Pdf\PdfService;
use Tpb\Domain\Status\Status;
use Tpb\Domain\Token\AccessTokenService;

/**
 * Proof-Zyklus (§7, M4): aus der finalen Artwork-Version einen Proof erzeugen und
 * versenden → Kundenfreigabe oder Änderungswunsch per Token. Eine neue Version löst
 * die vorherige ab (superseded) und erfordert eine neue Freigabe. Nur für Aufträge
 * mit konfigurierten Positionen; Standardartikel starten bereits bei LOCKED (v1.4).
 */
final class ProofService
{
    public const TOKEN_PURPOSE = 'proof_view';

    /**
     * Hinterlegt eine (finale/gereinigte) Artwork-Datei zum Auftrag und legt eine
     * neue Artwork-Version an. Erstupload setzt die Artwork-Achse MISSING → UPLOADED.
     *
     * @param array{name:string,type?:string,tmp_name:string,error:int,size:int} $file
     * @return array{version_no:int,asset_public_id:string}
     */
    public static function addArtwork(int $orderId, array $file, ?int $actorUserId): array
    {
        return Db::tx(function () use ($orderId, $file, $actorUserId): array {
            $order = Db::run('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }
            if ((string) $order['cur_artwork'] === 'LOCKED') {
                throw new ProofStateException('Das Artwork ist bereits freigegeben und gesperrt.');
            }

            $asset = AssetService::storeUpload($file, $actorUserId ?? 0, 'artwork', 'proof_contract');
            $ver = ArtworkRepo::addVersion($orderId, (int) $asset['id'], 'cleaned', $actorUserId);

            if ((string) $order['cur_artwork'] === 'MISSING') {
                Status::transition('order', $orderId, 'artwork', 'MISSING', 'UPLOADED', ['actor_user_id' => $actorUserId]);
                Db::run("UPDATE orders SET cur_artwork = 'UPLOADED', updated_at = ? WHERE id = ?", [Clock::nowUtcSeconds(), $orderId]);
            }

            Audit::log('order', (string) $order['public_id'], 'artwork.uploaded', [
                'actor_user_id' => $actorUserId,
                'metadata'      => ['version_no' => $ver['version_no'], 'asset' => $asset['public_id']],
            ]);

            return ['version_no' => $ver['version_no'], 'asset_public_id' => (string) $asset['public_id']];
        });
    }

    /**
     * Erzeugt aus der jüngsten Artwork-Version einen Proof, rendert das Proof-PDF,
     * versendet Token + Mail und setzt die Artwork-Achse auf PROOF_SENT.
     *
     * @return array{proof_id:int,version_no:int,token:string,pdf_public_id:string}
     */
    public static function createAndSend(int $orderId, ?int $actorUserId): array
    {
        return Db::tx(function () use ($orderId, $actorUserId): array {
            $order = Db::run('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }
            $curArtwork = (string) $order['cur_artwork'];
            if (!in_array($curArtwork, ['UPLOADED', 'CHANGES_REQUESTED'], true)) {
                throw new ProofStateException('Für diesen Auftrag kann derzeit kein Proof versendet werden (Status: ' . $curArtwork . ').');
            }

            $artwork = ArtworkRepo::latest($orderId);
            if ($artwork === null) {
                throw new ProofStateException('Es ist keine Artwork-Datei hinterlegt.');
            }

            // Vorherigen aktiven Proof ablösen.
            Db::run("UPDATE proofs SET status = 'superseded' WHERE order_id = ? AND status <> 'superseded'", [$orderId]);

            $versionNo = (int) Db::run('SELECT COALESCE(MAX(version_no), 0) + 1 FROM proofs WHERE order_id = ?', [$orderId])->fetchColumn();
            $now = Clock::nowUtcSeconds();

            // Proof zuerst einfügen, um die proof_id für Token/Referenz zu erhalten.
            Db::run(
                'INSERT INTO proofs (order_id, artwork_version_id, version_no, pdf_asset_id, status, sent_at, created_at)
                 VALUES (?, ?, ?, NULL, ?, ?, ?)',
                [$orderId, (int) $artwork['id'], $versionNo, 'draft', $now, $now]
            );
            $proofId = (int) Db::pdo()->lastInsertId();

            $pdfBytes = PdfService::render('proof', self::proofViewData($order, $artwork, $versionNo));
            $asset = AssetService::storeGenerated(
                $pdfBytes, 'Proof_' . (string) $order['order_number'] . '_v' . $versionNo . '.pdf',
                'application/pdf', 'proof_pdf', 'proof_contract', $actorUserId, 'customer', (int) $order['customer_id']
            );

            Db::run("UPDATE proofs SET pdf_asset_id = ?, status = 'sent' WHERE id = ?", [$asset['id'], $proofId]);

            // Artwork-Achse: UPLOADED → PREPRESS_REVIEW → PROOF_SENT, oder CHANGES_REQUESTED → PROOF_SENT.
            if ($curArtwork === 'UPLOADED') {
                Status::transition('order', $orderId, 'artwork', 'UPLOADED', 'PREPRESS_REVIEW', ['actor_user_id' => $actorUserId]);
                Status::transition('order', $orderId, 'artwork', 'PREPRESS_REVIEW', 'PROOF_SENT', ['actor_user_id' => $actorUserId]);
            } else {
                Status::transition('order', $orderId, 'artwork', 'CHANGES_REQUESTED', 'PROOF_SENT', ['actor_user_id' => $actorUserId]);
            }
            Db::run("UPDATE orders SET cur_artwork = 'PROOF_SENT', updated_at = ? WHERE id = ?", [$now, $orderId]);

            $token = AccessTokenService::issue(self::TOKEN_PURPOSE, 'proof', $proofId);

            $snapshot = json_decode((string) $order['customer_snapshot_json'], true) ?: [];
            $mail = [
                'order_public_id' => (string) $order['public_id'],
                'order_number'    => (string) $order['order_number'],
                'proof_version'   => $versionNo,
                'token'           => $token,
                'to_email'        => $snapshot['email'] ?? null,
                'to_name'         => trim((string) ($snapshot['first_name'] ?? '') . ' ' . (string) ($snapshot['last_name'] ?? '')),
            ];
            Outbox::enqueue('mail.proof_sent', array_merge($mail, MailTemplates::proofSent($mail)), 'proof_sent_' . $proofId);

            Audit::log('order', (string) $order['public_id'], 'proof.sent', [
                'actor_user_id' => $actorUserId,
                'to_state'      => 'PROOF_SENT',
                'metadata'      => ['proof_id' => $proofId, 'version_no' => $versionNo, 'pdf_sha256' => $asset['sha256']],
            ]);

            return ['proof_id' => $proofId, 'version_no' => $versionNo, 'token' => $token, 'pdf_public_id' => (string) $asset['public_id']];
        });
    }

    /**
     * Kundenfreigabe (§7-DoD): referenziert exakt den aktiven Proof (per Token gebunden).
     * Ein abgelöster/alter Proof-Link wird abgewiesen. Idempotent.
     */
    public static function approve(string $orderPublicId, string $rawToken): void
    {
        self::decide($orderPublicId, $rawToken, 'approved', null);
    }

    public static function requestChanges(string $orderPublicId, string $rawToken, ?string $comment): void
    {
        self::decide($orderPublicId, $rawToken, 'changes_requested', $comment);
    }

    private static function decide(string $orderPublicId, string $rawToken, string $decision, ?string $comment): void
    {
        Db::tx(function () use ($orderPublicId, $rawToken, $decision, $comment): void {
            $order = Db::run('SELECT * FROM orders WHERE public_id = ? FOR UPDATE', [$orderPublicId])->fetch();
            if ($order === false) {
                throw new ProofAccessException('Auftrag nicht gefunden.');
            }
            $orderId = (int) $order['id'];
            $proof = ProofRepo::activeForOrder($orderId);
            if ($proof === null) {
                throw new ProofAccessException('Kein aktiver Proof vorhanden.');
            }
            $proofId = (int) $proof['id'];

            $tokenRow = AccessTokenService::verify($rawToken, self::TOKEN_PURPOSE, 'proof', $proofId);
            if ($tokenRow === null) {
                throw new ProofAccessException('Der Proof-Link ist ungültig, abgelaufen oder wurde durch eine neue Version ersetzt.');
            }

            // Idempotenz: bereits entschieden.
            if ((string) $proof['status'] === 'approved' && $decision === 'approved') {
                return;
            }
            if ((string) $proof['status'] === 'changes_requested' && $decision === 'changes_requested') {
                return;
            }
            if ((string) $proof['status'] !== 'sent') {
                throw new ProofStateException('Dieser Proof kann nicht mehr bearbeitet werden (Status: ' . (string) $proof['status'] . ').');
            }

            $now = Clock::nowUtcSeconds();
            Db::run(
                'INSERT INTO proof_approvals (proof_id, decision, comment, actor_label, access_token_id, decided_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$proofId, $decision, $comment !== null && $comment !== '' ? mb_substr($comment, 0, 2000) : null, 'customer', (int) $tokenRow['id'], $now]
            );

            if ($decision === 'approved') {
                Db::run("UPDATE proofs SET status = 'approved' WHERE id = ?", [$proofId]);
                Status::transition('order', $orderId, 'artwork', 'PROOF_SENT', 'APPROVED', ['actor_label' => 'customer']);
                Status::transition('order', $orderId, 'artwork', 'APPROVED', 'LOCKED', ['actor_label' => 'customer']);
                Db::run("UPDATE orders SET cur_artwork = 'LOCKED', updated_at = ? WHERE id = ?", [$now, $orderId]);
                Audit::log('order', $orderPublicId, 'proof.approved', ['actor_label' => 'customer', 'from_state' => 'PROOF_SENT', 'to_state' => 'LOCKED', 'metadata' => ['proof_id' => $proofId, 'version_no' => (int) $proof['version_no']]]);
            } else {
                Db::run("UPDATE proofs SET status = 'changes_requested' WHERE id = ?", [$proofId]);
                Status::transition('order', $orderId, 'artwork', 'PROOF_SENT', 'CHANGES_REQUESTED', ['actor_label' => 'customer']);
                Db::run("UPDATE orders SET cur_artwork = 'CHANGES_REQUESTED', updated_at = ? WHERE id = ?", [$now, $orderId]);
                Audit::log('order', $orderPublicId, 'proof.changes_requested', ['actor_label' => 'customer', 'from_state' => 'PROOF_SENT', 'to_state' => 'CHANGES_REQUESTED', 'metadata' => ['proof_id' => $proofId, 'version_no' => (int) $proof['version_no']]]);
            }
        });
    }

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $artwork
     * @return array<string,mixed>
     */
    private static function proofViewData(array $order, array $artwork, int $versionNo): array
    {
        $items = [];
        foreach (OrderRepo::configuredItems((int) $order['id']) as $it) {
            $config = $it['config_snapshot_json'] !== null ? json_decode((string) $it['config_snapshot_json'], true) : null;
            $items[] = [
                'description' => (string) $it['description'],
                'qty'         => (int) $it['qty'],
                'layers'      => is_array($config) ? ($config['layers'] ?? []) : [],
            ];
        }
        return [
            'order'         => $order,
            'version_no'    => $versionNo,
            'artwork_name'  => (string) ($artwork['asset_id'] ?? ''),
            'customer'      => json_decode((string) $order['customer_snapshot_json'], true) ?: [],
            'items'         => $items,
        ];
    }
}
