<?php
declare(strict_types=1);

namespace Tpb\Http\Api;

use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Domain\Shop\ShopWebhookService;

/**
 * Zahlungs-Webhook-Endpunkt (§5.7). Verifiziert die Signatur vor jeder Verarbeitung
 * und antwortet idempotent. Auth ist die Signatur (kein CSRF – server-to-server).
 */
final class WebhookController
{
    /** @param array<string,string> $params */
    public function payment(array $params): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $signature = Request::header('X-Signature') ?? (string) ($_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? '');
        $result = ShopWebhookService::handle($raw, is_string($signature) ? $signature : '');
        Response::json(['status' => $result['status']], $result['http']);
    }
}
