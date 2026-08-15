<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Finance\Amount;
use Tpb\Domain\Pricing\PriceBookRepo;

/**
 * Preisbuch-Verwaltung (M1) mit Draft→Publish. Nach 'published' sind Tiers/Params
 * unveränderlich (§6.2, App-seitig erzwungen). Rechte: tpb_manage_pricing.
 * TODO(M7): Publish erfordert Sudo-Modus (Re-Auth, §11).
 */
final class PriceBookController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/pricing/books', [
            'title'       => 'Preisbücher',
            'nav'         => 'pricing',
            'books'       => PriceBookRepo::all(),
            'nextVersion' => PriceBookRepo::nextVersion(),
            'flash'       => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function store(array $params): void
    {
        $currency = strtoupper(trim((string) Request::post('currency', 'EUR')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }
        $version = PriceBookRepo::nextVersion();
        PriceBookRepo::create($version, $currency);
        Audit::log('price_book', (string) $version, 'pricing.book.created', ['actor_user_id' => Auth::id()]);
        $this->flash('ok', "Preisbuch v{$version} (Entwurf) angelegt.");
        Response::redirect('/admin/preisbuch/' . $version);
    }

    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $book = $this->requireBook($params);
        Response::html(View::render('admin/pricing/book', [
            'title'    => 'Preisbuch v' . $book['version'],
            'nav'      => 'pricing',
            'book'     => $book,
            'tiers'    => PriceBookRepo::listTiers((int) $book['id']),
            'params'   => PriceBookRepo::listParams((int) $book['id']),
            'products' => ProductRepo::all(),
            'isDraft'  => $book['status'] === 'draft',
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function addTier(array $params): void
    {
        $book = $this->requireDraft($params);
        try {
            $productId = (int) Request::post('product_id', '0');
            $qtyFrom = (int) Request::post('qty_from', '0');
            $qtyToRaw = trim((string) Request::post('qty_to', ''));
            $qtyTo = $qtyToRaw === '' ? null : (int) $qtyToRaw;
            $unitCents = Amount::toCents((string) Request::post('unit_price', ''));
            if ($productId <= 0 || $qtyFrom <= 0) {
                throw new \InvalidArgumentException('Produkt und Startmenge sind Pflicht.');
            }
            if ($qtyTo !== null && $qtyTo < $qtyFrom) {
                throw new \InvalidArgumentException('Bis-Menge darf nicht kleiner als Von-Menge sein.');
            }
            if (PriceBookRepo::tierStartExists((int) $book['id'], $productId, $qtyFrom)) {
                throw new \InvalidArgumentException('Für dieses Produkt existiert bereits eine Staffel mit dieser Von-Menge.');
            }
            PriceBookRepo::addTier((int) $book['id'], $productId, $qtyFrom, $qtyTo, $unitCents);
            Audit::log('price_book', (string) $book['version'], 'pricing.tier.added', ['actor_user_id' => Auth::id(), 'metadata' => ['product_id' => $productId, 'qty_from' => $qtyFrom]]);
            $this->flash('ok', 'Staffel hinzugefügt.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/preisbuch/' . $book['version']);
    }

    /** @param array<string,string> $params */
    public function deleteTier(array $params): void
    {
        $book = $this->requireDraft($params);
        $id = (int) Request::post('id', '0');
        if ($id > 0) {
            PriceBookRepo::deleteTier($id, (int) $book['id']);
            Audit::log('price_book', (string) $book['version'], 'pricing.tier.deleted', ['actor_user_id' => Auth::id(), 'metadata' => ['tier_id' => $id]]);
            $this->flash('ok', 'Staffel gelöscht.');
        }
        Response::redirect('/admin/preisbuch/' . $book['version']);
    }

    /** @param array<string,string> $params */
    public function setParam(array $params): void
    {
        $book = $this->requireDraft($params);
        try {
            $key = strtoupper(trim((string) Request::post('param_key', '')));
            if (!preg_match('/^[A-Z0-9_]{2,48}$/', $key)) {
                throw new \InvalidArgumentException('Ungültiger Parameter-Schlüssel.');
            }
            $value = (int) Request::post('value_int', '0');
            $note = trim((string) Request::post('note', ''));
            PriceBookRepo::setParam((int) $book['id'], $key, $value, $note === '' ? null : $note);
            Audit::log('price_book', (string) $book['version'], 'pricing.param.set', ['actor_user_id' => Auth::id(), 'metadata' => ['key' => $key, 'value' => $value]]);
            $this->flash('ok', 'Parameter gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/preisbuch/' . $book['version']);
    }

    /** @param array<string,string> $params */
    public function deleteParam(array $params): void
    {
        $book = $this->requireDraft($params);
        $id = (int) Request::post('id', '0');
        if ($id > 0) {
            PriceBookRepo::deleteParam($id, (int) $book['id']);
            Audit::log('price_book', (string) $book['version'], 'pricing.param.deleted', ['actor_user_id' => Auth::id(), 'metadata' => ['param_id' => $id]]);
            $this->flash('ok', 'Parameter gelöscht.');
        }
        Response::redirect('/admin/preisbuch/' . $book['version']);
    }

    /** @param array<string,string> $params */
    public function publish(array $params): void
    {
        $book = $this->requireBook($params);
        if ($book['status'] !== 'draft') {
            $this->flash('error', 'Nur Entwürfe können veröffentlicht werden.');
            Response::redirect('/admin/preisbuch/' . $book['version']);
            return;
        }
        if (PriceBookRepo::listTiers((int) $book['id']) === []) {
            $this->flash('error', 'Preisbuch ohne Staffeln kann nicht veröffentlicht werden.');
            Response::redirect('/admin/preisbuch/' . $book['version']);
            return;
        }
        PriceBookRepo::publish((int) $book['version'], Auth::id());
        Audit::log('price_book', (string) $book['version'], 'pricing.book.published', ['actor_user_id' => Auth::id(), 'to_state' => 'published']);
        $this->flash('ok', "Preisbuch v{$book['version']} veröffentlicht – Staffeln/Parameter sind jetzt unveränderlich.");
        Response::redirect('/admin/preisbuch/' . $book['version']);
    }

    /** @param array<string,string> $params */
    public function retire(array $params): void
    {
        $book = $this->requireBook($params);
        if ($book['status'] === 'published') {
            PriceBookRepo::retire((int) $book['version']);
            Audit::log('price_book', (string) $book['version'], 'pricing.book.retired', ['actor_user_id' => Auth::id(), 'to_state' => 'retired']);
            $this->flash('ok', 'Preisbuch stillgelegt.');
        }
        Response::redirect('/admin/preisbuch/' . $book['version']);
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function requireBook(array $params): array
    {
        $book = PriceBookRepo::findByVersion((int) ($params['version'] ?? 0));
        if ($book === null) {
            throw new HttpException(404, 'Preisbuch nicht gefunden.');
        }
        return $book;
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function requireDraft(array $params): array
    {
        $book = $this->requireBook($params);
        if ($book['status'] !== 'draft') {
            throw new HttpException(403, 'Veröffentlichtes Preisbuch ist unveränderlich (§6.2).');
        }
        return $book;
    }
}
