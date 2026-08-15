<?php
declare(strict_types=1);

namespace Tpb\Http\Api;

use Tpb\Core\Canonical;
use Tpb\Core\Response;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\File\UploadRejected;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingException;
use Tpb\Domain\Pricing\PricingRepo;

/**
 * Öffentliche Konfigurations-API (M2): Entwurf speichern (mit serverseitiger
 * Preisberechnung + Persistenz in price_calculations), laden und Gast-Logo-Upload.
 */
final class ConfigController
{
    /** @param array<string,string> $params */
    public function save(array $params): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            Response::json(['ok' => false, 'error' => 'Ungültige Anfrage (JSON erwartet).'], 400);
            return;
        }

        try {
            $resolved = ConfigMapper::resolve($data);
        } catch (\InvalidArgumentException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
            return;
        }

        $ids = PricingRepo::activeIds();
        $pb = PricingRepo::activePriceBook();
        $cv = PricingRepo::activeCostVersion();
        if ($ids === null || $pb === null || $cv === null) {
            Response::json(['ok' => false, 'error' => 'Kein veröffentlichtes Preisbuch vorhanden.'], 409);
            return;
        }

        $engineConfig = ConfigMapper::toEngineConfig($resolved);
        try {
            $breakdown = PriceEngine::calculate($engineConfig, $pb, $cv);
        } catch (PricingException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }

        try {
            $saved = ConfigurationRepo::save($resolved, [
                'price_book_id'   => $ids['price_book_id'],
                'cost_version_id' => $ids['cost_version_id'],
                'guest_email'     => $this->emailOrNull($data['guest_email'] ?? null),
                'guest_name'      => $this->str($data['guest_name'] ?? null, 120),
                'note'            => $this->str($data['note'] ?? null, 500),
                'public_id'       => isset($data['public_id']) ? (string) $data['public_id'] : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
            return;
        }

        ConfigurationRepo::persistCalculation(
            $saved['id'],
            $ids['price_book_id'],
            $ids['cost_version_id'],
            Canonical::hash($engineConfig),
            json_encode($breakdown->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $breakdown->totalCents,
            $breakdown->floorCents,
            $breakdown->belowFloor,
            $breakdown->calcHash
        );

        Audit::log('configuration', $saved['public_id'], 'config.saved', [
            'actor_label' => 'guest',
            'metadata'    => ['total_cents' => $breakdown->totalCents, 'calc_hash' => $breakdown->calcHash],
        ]);

        Response::json([
            'ok'              => true,
            'public_id'       => $saved['public_id'],
            'currency'        => $pb->currency,
            'total_cents'     => $breakdown->totalCents,
            'floor_cents'     => $breakdown->floorCents,
            'below_min_order' => $breakdown->belowMinOrder,
            'below_floor'     => $breakdown->belowFloor,
            'calc_hash'       => $breakdown->calcHash,
        ]);
    }

    /** @param array<string,string> $params */
    public function load(array $params): void
    {
        $config = ConfigurationRepo::loadByPublicId($params['publicId'] ?? '');
        if ($config === null) {
            Response::json(['ok' => false, 'error' => 'Entwurf nicht gefunden.'], 404);
            return;
        }
        Response::json(['ok' => true, 'config' => $config]);
    }

    /** @param array<string,string> $params */
    public function upload(array $params): void
    {
        $file = $_FILES['file'] ?? null;
        try {
            if (!is_array($file)) {
                throw new UploadRejected('Es wurde keine Datei ausgewählt.');
            }
            /** @var array{name:string,type?:string,tmp_name:string,error:int,size:int} $file */
            $asset = AssetService::storeUpload($file, 0, 'artwork', 'artwork_short');
        } catch (UploadRejected $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }

        Response::json([
            'ok'             => true,
            'asset_public_id' => $asset['public_id'],
            'original_name'  => $asset['original_name'],
            'mime'           => $asset['mime'],
        ]);
    }

    private function emailOrNull(mixed $v): ?string
    {
        if (!is_string($v) || trim($v) === '') {
            return null;
        }
        $v = trim($v);
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? mb_substr($v, 0, 190) : null;
    }

    private function str(mixed $v, int $max): ?string
    {
        if (!is_string($v) || trim($v) === '') {
            return null;
        }
        return mb_substr(trim($v), 0, $max);
    }
}
