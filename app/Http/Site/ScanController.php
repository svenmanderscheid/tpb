<?php
declare(strict_types=1);

namespace Tpb\Http\Site;

use Tpb\Core\Db;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Label\LabelService;
use Tpb\Domain\Production\JobRepo;
use Tpb\Domain\Token\AccessTokenService;

/**
 * Interne Scan-Ansicht eines Jobs per QR-Token (§9.2). Read-only Statuskarte für
 * die Werkstatt; Mutationen laufen über das (login-geschützte) Backoffice.
 */
final class ScanController
{
    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $job = JobRepo::findByPublicId((string) ($params['publicId'] ?? ''));
        $token = (string) (Request::query('t', '') ?? '');
        if ($job === null || AccessTokenService::verify($token, LabelService::SCAN_TOKEN_PURPOSE, 'production_job', (int) $job['id']) === null) {
            Response::html(View::render('site/scan_invalid', [], 'layout/site'), 404);
            return;
        }
        $order = Db::run('SELECT order_number FROM orders WHERE id = ? LIMIT 1', [(int) $job['order_id']])->fetch();
        $item = Db::run('SELECT description, qty FROM order_items WHERE id = ? LIMIT 1', [(int) $job['order_item_id']])->fetch();

        Response::html(View::render('site/scan_job', [
            'job'   => $job,
            'order' => $order,
            'item'  => $item,
        ], 'layout/site'));
    }
}
