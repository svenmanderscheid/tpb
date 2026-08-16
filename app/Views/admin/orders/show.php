<?php
/**
 * @var array<string,mixed> $order
 * @var array<string,mixed>|false $customer
 * @var array<int,array<string,mixed>> $items
 * @var array<int,array<string,mixed>> $artworks
 * @var array<int,array<string,mixed>> $proofs
 * @var array<string,mixed>|null $shipment
 * @var int|null $last_shipment_print
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$sh = $shipment ?? [];
$method = (string) ($sh['method'] ?? 'pickup');
$ful = (string) $order['cur_fulfillment'];
$fulLabels = ['UNFULFILLED' => 'Offen', 'PACKING' => 'Wird gepackt', 'READY_FOR_PICKUP' => 'Abholbereit', 'READY_TO_SHIP' => 'Versandbereit', 'SHIPPED' => 'Versendet', 'COLLECTED' => 'Abgeholt', 'DELIVERED' => 'Zugestellt'];
$isPickup = $method === 'pickup';
$shv = static fn (string $k): string => e((string) ($sh[$k] ?? ''));

$art = (string) $order['cur_artwork'];
$currency = (string) $order['currency'];
$artLabels = ['MISSING' => 'Artwork fehlt', 'UPLOADED' => 'Artwork hochgeladen', 'PREPRESS_REVIEW' => 'Prepress', 'PROOF_SENT' => 'Proof versendet', 'CHANGES_REQUESTED' => 'Änderung gewünscht', 'APPROVED' => 'Freigegeben', 'LOCKED' => 'Freigegeben (gesperrt)'];
$canProof = in_array($art, ['UPLOADED', 'CHANGES_REQUESTED'], true);
$proofLabels = ['draft' => 'Entwurf', 'sent' => 'Versendet', 'approved' => 'Freigegeben', 'changes_requested' => 'Änderung gewünscht', 'superseded' => 'Abgelöst'];
?>
<p><a href="/admin/auftraege">← Aufträge</a></p>
<h1>Auftrag <?= e((string) $order['order_number']) ?>
    <span class="status-badge <?= e(strtolower($art)) ?>"><?= e($artLabels[$art] ?? $art) ?></span>
</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Positionen</h2>
        <table>
            <thead><tr><th>Pos</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Gesamt</th></tr></thead>
            <tbody>
                <?php foreach ($items as $l): ?>
                    <tr>
                        <td><?= e((string) $l['pos_no']) ?></td>
                        <td><?= e((string) $l['description']) ?><?= $l['config_snapshot_json'] === null ? ' <span class="badge">Standard</span>' : '' ?></td>
                        <td class="num"><?= e((string) $l['qty']) ?></td>
                        <td class="num"><?= e(Money::format((int) $l['line_cents'], $currency)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" class="num"><strong>Gesamt</strong></td><td class="num"><strong><?= e(Money::format((int) $order['total_cents'], $currency)) ?></strong></td></tr></tfoot>
        </table>

        <h2 class="mt">Proofs</h2>
        <?php if (empty($proofs)): ?>
            <p class="muted">Noch kein Proof erstellt.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Version</th><th>Status</th><th>Versendet</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($proofs as $p): ?>
                        <tr>
                            <td>v<?= e((string) $p['version_no']) ?></td>
                            <td><span class="status-badge <?= e(strtolower((string) $p['status'])) ?>"><?= e($proofLabels[(string) $p['status']] ?? (string) $p['status']) ?></span></td>
                            <td><?= e((string) substr((string) ($p['sent_at'] ?? ''), 0, 16)) ?></td>
                            <td class="num"><?php if ($p['pdf_public_id'] !== null): ?><a href="/files/<?= e((string) $p['pdf_public_id']) ?>">PDF</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <aside>
        <div class="card">
            <h2>Kunde</h2>
            <?php if ($customer !== false): ?>
                <?php if (!empty($customer['company_name'])): ?><p><strong><?= e((string) $customer['company_name']) ?></strong></p><?php endif; ?>
                <p><?= e(trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name'])) ?><br><?= e((string) $customer['email']) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Artwork</h2>
            <?php if ($art === 'LOCKED'): ?>
                <p class="ok-text">Artwork ist freigegeben und gesperrt.</p>
            <?php else: ?>
                <form method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/artwork" enctype="multipart/form-data">
                    <?= Csrf::field() ?>
                    <div class="field"><label for="file">Finale Druckdatei (svg/pdf/png/jpg)</label><input type="file" id="file" name="file"></div>
                    <button type="submit" class="btn secondary">Artwork hochladen</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($artworks)): ?>
                <p class="muted mt">Versionen:</p>
                <ul>
                    <?php foreach ($artworks as $a): ?>
                        <li>v<?= e((string) $a['version_no']) ?> · <a href="/files/<?= e((string) $a['asset_public_id']) ?>"><?= e((string) $a['original_name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Produktion</h2>
            <form method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/produktion">
                <?= Csrf::field() ?>
                <button type="submit" class="btn secondary">Produktionsjobs anlegen</button>
            </form>
            <p class="muted mt"><a href="/admin/produktion">Zur Produktionswarteschlange →</a></p>
        </div>

        <div class="card">
            <h2>Proof senden</h2>
            <?php if ($canProof): ?>
                <form method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/proof">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn">Proof erstellen &amp; versenden</button>
                </form>
                <p class="muted mt">Rendert das Proof-PDF und schickt dem Kunden den Freigabelink.</p>
            <?php elseif ($art === 'MISSING'): ?>
                <p class="muted">Bitte zuerst eine Artwork-Datei hochladen.</p>
            <?php elseif ($art === 'PROOF_SENT'): ?>
                <p class="muted">Proof ist versendet und wartet auf Kundenentscheidung.</p>
            <?php elseif ($art === 'LOCKED'): ?>
                <p class="muted">Bereits freigegeben.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Versand <span class="status-badge <?= e(strtolower($ful)) ?>"><?= e($fulLabels[$ful] ?? $ful) ?></span></h2>

            <form method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/versand">
                <?= Csrf::field() ?>
                <div class="field">
                    <label for="method">Versandart</label>
                    <select id="method" name="method">
                        <option value="pickup" <?= $method === 'pickup' ? 'selected' : '' ?>>Abholung</option>
                        <option value="self_premium" <?= $method === 'self_premium' ? 'selected' : '' ?>>Eigenlieferung (Premium)</option>
                        <option value="carrier_standard" <?= $method === 'carrier_standard' ? 'selected' : '' ?>>Standardversand (günstigster Anbieter)</option>
                    </select>
                </div>
                <div class="field-row">
                    <div class="field"><label for="carrier">Anbieter (Standardversand)</label><input type="text" id="carrier" name="carrier" value="<?= $shv('carrier') ?>"></div>
                    <div class="field"><label for="shipping_cost">Versandkosten €</label><input type="text" id="shipping_cost" name="shipping_cost" value="<?= e(number_format((int) ($sh['shipping_cost_cents'] ?? 0) / 100, 2, ',', '')) ?>"></div>
                </div>
                <div class="field"><label for="recipient_company">Empfänger Firma</label><input type="text" id="recipient_company" name="recipient_company" value="<?= $shv('recipient_company') ?>"></div>
                <div class="field"><label for="recipient_name">Empfänger Name</label><input type="text" id="recipient_name" name="recipient_name" value="<?= $shv('recipient_name') ?>"></div>
                <div class="field"><label for="street">Straße</label><input type="text" id="street" name="street" value="<?= $shv('street') ?>"></div>
                <div class="field-row">
                    <div class="field"><label for="zip">PLZ</label><input type="text" id="zip" name="zip" value="<?= $shv('zip') ?>"></div>
                    <div class="field"><label for="city">Ort</label><input type="text" id="city" name="city" value="<?= $shv('city') ?>"></div>
                    <div class="field"><label for="country">Land</label><input type="text" id="country" name="country" value="<?= $shv('country') ?>"></div>
                </div>
                <button type="submit" class="btn secondary">Versanddaten speichern</button>
            </form>

            <?php
            $step = null;
            if ($ful === 'UNFULFILLED') { $step = ['pack', 'Packen']; }
            elseif ($ful === 'PACKING') { $step = ['ready', $isPickup ? 'Abholbereit' : 'Versandbereit']; }
            elseif ($ful === 'READY_FOR_PICKUP') { $step = ['collect', 'Als abgeholt markieren']; }
            elseif ($ful === 'READY_TO_SHIP') { $step = ['ship', 'Als versendet markieren']; }
            elseif ($ful === 'SHIPPED') { $step = ['deliver', 'Als zugestellt markieren']; }
            ?>
            <div class="actions-row mt">
                <?php if ($step !== null): ?>
                    <form class="inline-form" method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/versand/status">
                        <?= Csrf::field() ?><input type="hidden" name="action" value="<?= e($step[0]) ?>">
                        <button type="submit" class="btn"><?= e($step[1]) ?></button>
                    </form>
                <?php else: ?>
                    <p class="muted">Versand abgeschlossen.</p>
                <?php endif; ?>
            </div>

            <?php if (!$isPickup): ?>
                <form method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/versand/etikett" class="mt"><?= Csrf::field() ?><button type="submit" class="btn secondary">Versandetikett rendern</button></form>
                <?php if ($last_shipment_print !== null): ?>
                    <form method="post" action="/admin/auftrag/<?= e((string) $order['public_id']) ?>/versand/etikett/neu" class="mt">
                        <?= Csrf::field() ?><input type="hidden" name="reprint_of" value="<?= e((string) $last_shipment_print) ?>">
                        <div class="field"><label for="sreason">Grund für Neudruck</label><input type="text" id="sreason" name="reason"></div>
                        <button type="submit" class="btn secondary">Etikett neu drucken</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>
