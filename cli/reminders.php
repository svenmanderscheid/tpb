<?php
declare(strict_types=1);

/**
 * Mahnwesen & Wiedervorlage (§5.6/§12). Versendet Zahlungserinnerungen (Stufe 1) für
 * überfällige Rechnungen und Wiedervorlage-Hinweise für bald ablaufende Angebote –
 * jede Stufe genau einmal (reminders_sent). Idempotent bei mehrfachem Lauf.
 *
 * Aufruf: php cli/reminders.php
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Domain\Dunning\DunningService;

$r = DunningService::run();
echo "Mahnwesen: {$r['payment_reminders']} Zahlungserinnerung(en), {$r['quote_followups']} Wiedervorlage(n).\n";
