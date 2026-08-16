<?php
declare(strict_types=1);

namespace Tpb\Http\Site;

use Tpb\Core\HttpException;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Legal\LegalDocRepo;

/**
 * Öffentliche Konfigurator-Seiten (M2). Namespace Http/Site (public ist als
 * PHP-Namespace reserviert, siehe docs/DECISIONS.md #19).
 */
final class SiteController
{
    /** @param array<string,string> $params */
    public function home(array $params): void
    {
        Response::html(View::render('site/home', [
            'products'  => $this->activeProducts(),
            'legalDocs' => LegalDocRepo::allPublished('de'),
        ], null));
    }

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('site/index', ['products' => $this->activeProducts()], null));
    }

    /** @param array<string,string> $params */
    public function legal(array $params): void
    {
        $docType = (string) ($params['docType'] ?? '');
        $doc = $docType !== '' ? LegalDocRepo::published($docType, 'de') : null;
        if ($docType !== '' && $doc === null) {
            throw new HttpException(404, 'Rechtstext nicht gefunden.');
        }
        Response::html(View::render('site/legal', [
            'docs' => LegalDocRepo::allPublished('de'),
            'doc'  => $doc,
        ], null));
    }

    /** @return array<int,array<string,mixed>> */
    private function activeProducts(): array
    {
        return array_values(array_filter(
            ProductRepo::all(),
            static fn ($p) => $p['status'] === 'active'
        ));
    }

    /** @param array<string,string> $params */
    public function configurator(array $params): void
    {
        $product = ProductRepo::findByPublicId($params['publicId'] ?? '');
        if ($product === null || $product['status'] !== 'active') {
            throw new HttpException(404, 'Produkt nicht gefunden.');
        }
        $productId = (int) $product['id'];

        $variants = array_values(array_filter(
            VariantRepo::listByProduct($productId),
            static fn ($v) => $v['status'] === 'active'
        ));
        $techniques = array_values(array_filter(
            TechniqueRepo::all(),
            static fn ($t) => $t['status'] === 'active'
        ));

        $data = [
            'product'    => [
                'public_id' => (string) $product['public_id'],
                'name'      => (string) $product['name'],
                'type'      => (string) $product['product_type'],
            ],
            'variants'   => array_map(static fn ($v) => [
                'sku'        => (string) $v['sku'],
                'color_name' => (string) ($v['color_name'] ?? ''),
                'color_code' => (string) ($v['color_code'] ?? ''),
                'size'       => (string) ($v['size'] ?? ''),
            ], $variants),
            'placements' => array_map(static fn ($p) => [
                'code'     => (string) $p['code'],
                'name'     => (string) $p['name'],
                'side'     => (string) $p['side'],
                'max_w_mm' => $p['max_w_mm'],
                'max_h_mm' => $p['max_h_mm'],
            ], PlacementRepo::listByProduct($productId)),
            'techniques' => array_map(static fn ($t) => [
                'code' => (string) $t['code'],
                'name' => (string) $t['name'],
            ], $techniques),
        ];

        Response::html(View::render('site/configurator', [
            'product'  => $product,
            'dataJson' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        ], null));
    }
}
