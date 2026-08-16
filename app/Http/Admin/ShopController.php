<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Db;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Payment\PaymentIntentRepo;

/**
 * Backoffice-Einsicht in Shop-Zahlungen (§5.7): Zahlungsabsichten und rohe
 * Webhook-Events. Recht: tpb_view_costs (Lesen).
 */
final class ShopController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function payments(array $params): void
    {
        $events = Db::run(
            'SELECT provider, event_ref, event_type, signature_valid, process_status, received_at
             FROM payment_webhook_events ORDER BY id DESC LIMIT 100'
        )->fetchAll();

        Response::html(View::render('admin/shop/payments', [
            'title'   => 'Shop-Zahlungen',
            'nav'     => 'shoppay',
            'intents' => PaymentIntentRepo::listForAdmin(),
            'events'  => $events,
            'flash'   => $this->takeFlash(),
        ]));
    }
}
