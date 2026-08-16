<?php
declare(strict_types=1);

namespace Tpb\Http\Site;

use Tpb\Core\Db;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\File\PrivateStorage;
use Tpb\Domain\Quote\QuoteAccessException;
use Tpb\Domain\Quote\QuoteRepo;
use Tpb\Domain\Quote\QuoteService;
use Tpb\Domain\Quote\QuoteStateException;
use Tpb\Domain\Token\AccessTokenService;

/**
 * Kundenansicht eines Angebots per Token (§7, M3). Der Token in ?t=… ist die
 * Zugriffskontrolle (fortlaufende Nummern sind nie Schutz, §11). Annahme/Ablehnung
 * über QuoteService (idempotent). PDF wird nach Tokenprüfung als Download gestreamt.
 */
final class QuoteViewController
{
    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $publicId = (string) ($params['publicId'] ?? '');
        $token = (string) (Request::query('t', '') ?? '');
        $quote = QuoteRepo::findByPublicId($publicId);

        if ($quote === null || AccessTokenService::verify($token, QuoteService::TOKEN_PURPOSE, 'quote', (int) $quote['id']) === null) {
            Response::html(View::render('site/quote_invalid', [], null), 404);
            return;
        }

        $snapshot = json_decode((string) $quote['snapshot_json'], true);
        $order = null;
        if ((string) $quote['status'] === 'ACCEPTED') {
            $order = Db::run('SELECT order_number FROM orders WHERE quote_id = ? LIMIT 1', [(int) $quote['id']])->fetch() ?: null;
        }

        Response::html(View::render('site/quote_view', [
            'quote'    => $quote,
            'items'    => QuoteRepo::items((int) $quote['id']),
            'snapshot' => $snapshot,
            'token'    => $token,
            'order'    => $order,
        ], null));
    }

    /** @param array<string,string> $params */
    public function accept(array $params): void
    {
        $publicId = (string) ($params['publicId'] ?? '');
        $token = (string) (Request::post('t', '') ?? '');
        try {
            $result = QuoteService::accept($publicId, $token);
            Response::html(View::render('site/quote_result', [
                'kind'         => 'accepted',
                'order_number' => $result['order_number'],
            ], null));
        } catch (QuoteAccessException) {
            Response::html(View::render('site/quote_invalid', [], null), 404);
        } catch (QuoteStateException $e) {
            Response::html(View::render('site/quote_result', ['kind' => 'error', 'message' => $e->getMessage()], null), 409);
        }
    }

    /** @param array<string,string> $params */
    public function decline(array $params): void
    {
        $publicId = (string) ($params['publicId'] ?? '');
        $token = (string) (Request::post('t', '') ?? '');
        try {
            QuoteService::decline($publicId, $token);
            Response::html(View::render('site/quote_result', ['kind' => 'declined'], null));
        } catch (QuoteAccessException) {
            Response::html(View::render('site/quote_invalid', [], null), 404);
        } catch (QuoteStateException $e) {
            Response::html(View::render('site/quote_result', ['kind' => 'error', 'message' => $e->getMessage()], null), 409);
        }
    }

    /** Streamt das Angebots-PDF nach Tokenprüfung (Attachment, nosniff). */
    public function pdf(array $params): void
    {
        $publicId = (string) ($params['publicId'] ?? '');
        $token = (string) (Request::query('t', '') ?? '');
        $quote = QuoteRepo::findByPublicId($publicId);

        if ($quote === null || $quote['pdf_asset_id'] === null
            || AccessTokenService::verify($token, QuoteService::TOKEN_PURPOSE, 'quote', (int) $quote['id']) === null) {
            Response::error(404);
            return;
        }

        $asset = Db::run('SELECT original_name, storage_key, security_status FROM assets WHERE id = ? LIMIT 1', [(int) $quote['pdf_asset_id']])->fetch();
        if (!$asset || $asset['security_status'] !== 'clean' || !PrivateStorage::exists((string) $asset['storage_key'])) {
            Response::error(404);
            return;
        }

        $path = PrivateStorage::path((string) $asset['storage_key']);
        Response::sendSecurityHeaders();
        http_response_code(200);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $asset['original_name']) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
    }
}
