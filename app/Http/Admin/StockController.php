<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Stock\StockException;
use Tpb\Domain\Stock\StockRepo;
use Tpb\Domain\Stock\StockService;

/**
 * Backoffice: Lager/Bestand (DECISIONS #31). Sofort-Übersicht, Wareneingang/Korrektur,
 * Reserve und Meldebestand je Variante sowie „für alle setzen". Recht: tpb_manage_pricing.
 */
final class StockController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/stock/index', [
            'title'    => 'Lager',
            'nav'      => 'stock',
            'variants' => StockRepo::listForAdmin(),
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function update(array $params): void
    {
        $variantId = (int) ($params['id'] ?? 0);
        if (StockRepo::variant($variantId) === null) {
            $this->flash('error', 'Variante nicht gefunden.');
            Response::redirect('/admin/lager');
            return;
        }
        $action = (string) Request::post('action', '');
        $val = (int) Request::post('value', '0');
        try {
            match ($action) {
                'bestand'      => StockService::adjustTo($variantId, max(0, $val), Auth::id(), 'Korrektur im Backoffice'),
                'wareneingang' => StockService::receive($variantId, $val, Auth::id(), 'Wareneingang'),
                'reserve'      => StockService::setReserve($variantId, $val, Auth::id()),
                'meldebestand' => StockService::setReorderThreshold($variantId, $val, Auth::id()),
                default        => throw new StockException('Unbekannte Aktion.'),
            };
            $this->flash('ok', 'Bestand aktualisiert.');
        } catch (StockException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/lager');
    }

    /** @param array<string,string> $params */
    public function bulkThreshold(array $params): void
    {
        $n = StockService::bulkSetReorderThreshold((int) Request::post('value', '0'), Auth::id());
        $this->flash('ok', "Meldebestand für {$n} Variante(n) gesetzt.");
        Response::redirect('/admin/lager');
    }
}
