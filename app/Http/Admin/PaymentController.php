<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Clock;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Payment\PaymentService;

/**
 * Backoffice: Zahlungserfassung je Auftrag (M6). Recht: tpb_manage_finance.
 * Die Zahlungsachse wird serverseitig abgeleitet (§7).
 */
final class PaymentController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function record(array $params): void
    {
        $order = OrderRepo::findByPublicId($params['publicId'] ?? '');
        if ($order === null) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        try {
            $amount = $this->eurosToCents((string) Request::post('amount', ''));
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Bitte einen gültigen Betrag angeben.');
            }
            $date = trim((string) Request::post('received_at', '')) ?: Clock::nowUtc()->format('Y-m-d');
            PaymentService::record(
                (int) $order['id'],
                (string) Request::post('method', 'bank_transfer'),
                $amount,
                $date,
                trim((string) Request::post('reference', '')) ?: null,
                Auth::id()
            );
            $this->flash('ok', 'Zahlung erfasst.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Zahlung konnte nicht erfasst werden: ' . $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    private function eurosToCents(string $raw): int
    {
        $t = str_replace([' ', "\u{00a0}", ','], ['', '', '.'], trim($raw));
        return $t === '' || !is_numeric($t) ? 0 : (int) round((float) $t * 100);
    }
}
