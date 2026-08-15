<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\VariantRepo;

/**
 * Admin-CRUD Katalog (M1): Produkte, Varianten, Placements.
 * Rechte: tpb_manage_pricing (owner/admin). Jede Mutation: CSRF + Audit.
 */
final class CatalogController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function products(array $params): void
    {
        Response::html(View::render('admin/catalog/products', [
            'title'    => 'Katalog',
            'nav'      => 'catalog',
            'products' => ProductRepo::all(),
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function storeProduct(array $params): void
    {
        try {
            $type = (string) Request::post('product_type', '');
            if (!in_array($type, ProductRepo::TYPES, true)) {
                throw new \InvalidArgumentException('Ungültiger Produkttyp.');
            }
            $name = trim((string) Request::post('name', ''));
            $skuRoot = strtoupper(trim((string) Request::post('sku_root', '')));
            if ($name === '' || $skuRoot === '') {
                throw new \InvalidArgumentException('Name und SKU-Wurzel sind Pflicht.');
            }
            if (ProductRepo::skuRootExists($skuRoot)) {
                throw new \InvalidArgumentException('SKU-Wurzel existiert bereits.');
            }
            $slug = ProductRepo::slugify($name);
            $publicId = ProductRepo::create($type, $skuRoot, mb_substr($name, 0, 160), $slug, null, Auth::id());
            Audit::log('product', $publicId, 'catalog.product.created', ['actor_user_id' => Auth::id(), 'metadata' => ['type' => $type, 'sku_root' => $skuRoot]]);
            $this->flash('ok', 'Produkt angelegt.');
            Response::redirect('/admin/katalog/produkt/' . $publicId);
            return;
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/katalog');
    }

    /** @param array<string,string> $params */
    public function product(array $params): void
    {
        $product = $this->requireProduct($params['publicId'] ?? '');
        Response::html(View::render('admin/catalog/product', [
            'title'      => 'Produkt: ' . $product['name'],
            'nav'        => 'catalog',
            'product'    => $product,
            'variants'   => VariantRepo::listByProduct((int) $product['id']),
            'placements' => PlacementRepo::listByProduct((int) $product['id']),
            'sides'      => PlacementRepo::SIDES,
            'flash'      => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function updateProduct(array $params): void
    {
        $product = $this->requireProduct($params['publicId'] ?? '');
        try {
            $type = (string) Request::post('product_type', '');
            $status = (string) Request::post('status', '');
            if (!in_array($type, ProductRepo::TYPES, true) || !in_array($status, ProductRepo::STATUSES, true)) {
                throw new \InvalidArgumentException('Ungültiger Typ oder Status.');
            }
            $name = trim((string) Request::post('name', ''));
            $skuRoot = strtoupper(trim((string) Request::post('sku_root', '')));
            if ($name === '' || $skuRoot === '') {
                throw new \InvalidArgumentException('Name und SKU-Wurzel sind Pflicht.');
            }
            if (ProductRepo::skuRootExists($skuRoot, (int) $product['id'])) {
                throw new \InvalidArgumentException('SKU-Wurzel existiert bereits.');
            }
            $sort = (int) Request::post('sort', '0');
            $desc = trim((string) Request::post('description_md', ''));
            ProductRepo::update((string) $product['public_id'], $type, $skuRoot, mb_substr($name, 0, 160), ProductRepo::slugify($name), $desc === '' ? null : $desc, $status, $sort);
            Audit::log('product', (string) $product['public_id'], 'catalog.product.updated', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', 'Produkt gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/katalog/produkt/' . $product['public_id']);
    }

    /** @param array<string,string> $params */
    public function addVariant(array $params): void
    {
        $product = $this->requireProduct($params['publicId'] ?? '');
        try {
            $sku = strtoupper(trim((string) Request::post('sku', '')));
            if ($sku === '') {
                throw new \InvalidArgumentException('SKU ist Pflicht.');
            }
            if (VariantRepo::skuExists($sku)) {
                throw new \InvalidArgumentException('SKU existiert bereits.');
            }
            VariantRepo::create(
                (int) $product['id'],
                $sku,
                $this->nullTrim((string) Request::post('color_code', '')),
                $this->nullTrim((string) Request::post('color_name', '')),
                $this->nullTrim((string) Request::post('size', ''))
            );
            Audit::log('product', (string) $product['public_id'], 'catalog.variant.created', ['actor_user_id' => Auth::id(), 'metadata' => ['sku' => $sku]]);
            $this->flash('ok', 'Variante angelegt.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/katalog/produkt/' . $product['public_id']);
    }

    /** @param array<string,string> $params */
    public function deleteVariant(array $params): void
    {
        $product = $this->requireProduct($params['publicId'] ?? '');
        $id = (int) Request::post('id', '0');
        if ($id > 0) {
            VariantRepo::delete($id, (int) $product['id']);
            Audit::log('product', (string) $product['public_id'], 'catalog.variant.deleted', ['actor_user_id' => Auth::id(), 'metadata' => ['variant_id' => $id]]);
            $this->flash('ok', 'Variante gelöscht.');
        }
        Response::redirect('/admin/katalog/produkt/' . $product['public_id']);
    }

    /** @param array<string,string> $params */
    public function addPlacement(array $params): void
    {
        $product = $this->requireProduct($params['publicId'] ?? '');
        try {
            $side = (string) Request::post('side', '');
            if (!in_array($side, PlacementRepo::SIDES, true)) {
                throw new \InvalidArgumentException('Ungültige Seite.');
            }
            $code = strtolower(trim((string) Request::post('code', '')));
            $name = trim((string) Request::post('name', ''));
            if ($code === '' || $name === '') {
                throw new \InvalidArgumentException('Code und Name sind Pflicht.');
            }
            if (PlacementRepo::codeExistsForProduct((int) $product['id'], $code)) {
                throw new \InvalidArgumentException('Code existiert für dieses Produkt bereits.');
            }
            $dims = [
                'max_w_mm' => $this->mmOrNull((string) Request::post('max_w_mm', '')),
                'max_h_mm' => $this->mmOrNull((string) Request::post('max_h_mm', '')),
            ];
            PlacementRepo::create((int) $product['id'], $side, mb_substr($code, 0, 32), mb_substr($name, 0, 80), $dims, Request::post('is_preset') === '1');
            Audit::log('product', (string) $product['public_id'], 'catalog.placement.created', ['actor_user_id' => Auth::id(), 'metadata' => ['code' => $code]]);
            $this->flash('ok', 'Position angelegt.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/katalog/produkt/' . $product['public_id']);
    }

    /** @param array<string,string> $params */
    public function deletePlacement(array $params): void
    {
        $product = $this->requireProduct($params['publicId'] ?? '');
        $id = (int) Request::post('id', '0');
        if ($id > 0) {
            PlacementRepo::delete($id, (int) $product['id']);
            Audit::log('product', (string) $product['public_id'], 'catalog.placement.deleted', ['actor_user_id' => Auth::id(), 'metadata' => ['placement_id' => $id]]);
            $this->flash('ok', 'Position gelöscht.');
        }
        Response::redirect('/admin/katalog/produkt/' . $product['public_id']);
    }

    /**
     * @param string $publicId
     * @return array<string,mixed>
     */
    private function requireProduct(string $publicId): array
    {
        $product = ProductRepo::findByPublicId($publicId);
        if ($product === null) {
            throw new HttpException(404, 'Produkt nicht gefunden.');
        }
        return $product;
    }

    private function nullTrim(string $raw): ?string
    {
        $t = trim($raw);
        return $t === '' ? null : mb_substr($t, 0, 48);
    }

    private function mmOrNull(string $raw): ?string
    {
        $t = str_replace(',', '.', trim($raw));
        if ($t === '' || !is_numeric($t)) {
            return null;
        }
        return number_format(round((float) $t, 1), 1, '.', '');
    }
}
