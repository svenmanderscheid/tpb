<?php
declare(strict_types=1);

namespace Tpb\Http\Site;

use Tpb\Core\Db;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\File\PrivateStorage;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Proof\ProofAccessException;
use Tpb\Domain\Proof\ProofRepo;
use Tpb\Domain\Proof\ProofService;
use Tpb\Domain\Proof\ProofStateException;
use Tpb\Domain\Token\AccessTokenService;

/**
 * Kundenansicht eines Proofs per Token (§7, M4). Der Token bindet an genau eine
 * (aktive) Proof-Version; abgelöste Versionen weisen den alten Link ab.
 */
final class ProofViewController
{
    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        [$order, $proof, $token] = $this->resolve($params, (string) (Request::query('t', '') ?? ''));
        if ($order === null) {
            Response::html(View::render('site/proof_invalid', [], null), 404);
            return;
        }

        Response::html(View::render('site/proof_view', [
            'order' => $order,
            'proof' => $proof,
            'items' => OrderRepo::configuredItems((int) $order['id']),
            'token' => $token,
        ], null));
    }

    /** @param array<string,string> $params */
    public function approve(array $params): void
    {
        $publicId = (string) ($params['publicId'] ?? '');
        try {
            ProofService::approve($publicId, (string) (Request::post('t', '') ?? ''));
            Response::html(View::render('site/proof_result', ['kind' => 'approved'], null));
        } catch (ProofAccessException) {
            Response::html(View::render('site/proof_invalid', [], null), 404);
        } catch (ProofStateException $e) {
            Response::html(View::render('site/proof_result', ['kind' => 'error', 'message' => $e->getMessage()], null), 409);
        }
    }

    /** @param array<string,string> $params */
    public function changes(array $params): void
    {
        $publicId = (string) ($params['publicId'] ?? '');
        try {
            ProofService::requestChanges($publicId, (string) (Request::post('t', '') ?? ''), (string) (Request::post('comment', '') ?? ''));
            Response::html(View::render('site/proof_result', ['kind' => 'changes'], null));
        } catch (ProofAccessException) {
            Response::html(View::render('site/proof_invalid', [], null), 404);
        } catch (ProofStateException $e) {
            Response::html(View::render('site/proof_result', ['kind' => 'error', 'message' => $e->getMessage()], null), 409);
        }
    }

    /** @param array<string,string> $params */
    public function pdf(array $params): void
    {
        [$order, $proof] = $this->resolve($params, (string) (Request::query('t', '') ?? ''));
        if ($order === null || $proof === null || $proof['pdf_asset_id'] === null) {
            Response::error(404);
            return;
        }
        $asset = Db::run('SELECT original_name, storage_key, security_status FROM assets WHERE id = ? LIMIT 1', [(int) $proof['pdf_asset_id']])->fetch();
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

    /**
     * @param array<string,string> $params
     * @return array{0:?array<string,mixed>,1:?array<string,mixed>,2:string}
     */
    private function resolve(array $params, string $token): array
    {
        $order = OrderRepo::findByPublicId((string) ($params['publicId'] ?? ''));
        if ($order === null) {
            return [null, null, $token];
        }
        $proof = ProofRepo::activeForOrder((int) $order['id']);
        if ($proof === null || AccessTokenService::verify($token, ProofService::TOKEN_PURPOSE, 'proof', (int) $proof['id']) === null) {
            return [null, null, $token];
        }
        return [$order, $proof, $token];
    }
}
