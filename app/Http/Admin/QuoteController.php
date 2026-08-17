<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Quote\QuoteRepo;
use Tpb\Domain\Quote\QuoteService;

/**
 * Backoffice-Verwaltung der Angebotsanfragen und Angebote (M3).
 * Recht: tpb_manage_quotes (owner/admin/sales). Jede Mutation: CSRF + Audit (im Service).
 */
final class QuoteController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/quotes/index', [
            'title'    => 'Angebote',
            'nav'      => 'quotes',
            'requests' => QuoteRepo::openRequests(),
            'quotes'   => QuoteRepo::listForAdmin(),
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function createQuote(array $params): void
    {
        $configPublic = (string) ($params['publicId'] ?? '');
        $row = Db::run("SELECT id, status FROM configurations WHERE public_id = ? LIMIT 1", [$configPublic])->fetch();
        if ($row === false) {
            throw new HttpException(404, 'Konfiguration nicht gefunden.');
        }
        try {
            $quote = QuoteService::createFromConfiguration((int) $row['id'], Auth::id());
            $this->flash('ok', 'Angebot als Entwurf erstellt. Bitte prüfen und versenden.');
            Response::redirect('/admin/angebot/' . $quote['public_id']);
            return;
        } catch (\Throwable $e) {
            $this->flash('error', 'Angebot konnte nicht erstellt werden: ' . $e->getMessage());
            Response::redirect('/admin/anfragen');
        }
    }

    /** Nachtragsangebot aus einem Auftrag (§7, M9): referenziert die Order, Preisbuch aktuell. @param array<string,string> $params */
    public function createAmend(array $params): void
    {
        $order = Db::run('SELECT id, public_id FROM orders WHERE public_id = ? LIMIT 1', [$params['publicId'] ?? ''])->fetch();
        if ($order === false) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        $configPublic = trim((string) Request::post('config', ''));
        $cfg = $configPublic !== '' ? Db::run('SELECT id FROM configurations WHERE public_id = ? LIMIT 1', [$configPublic])->fetch() : false;
        if ($cfg === false) {
            $this->flash('error', 'Konfiguration (Entwurf) nicht gefunden. Bitte zuerst im Konfigurator die Nachtragspositionen speichern und die Entwurfs-ID eintragen.');
            Response::redirect('/admin/auftrag/' . $order['public_id']);
            return;
        }
        try {
            $quote = QuoteService::createFromConfiguration((int) $cfg['id'], Auth::id(), (int) $order['id']);
            $this->flash('ok', 'Nachtragsangebot als Entwurf erstellt. Bitte prüfen und versenden.');
            Response::redirect('/admin/angebot/' . $quote['public_id']);
            return;
        } catch (\Throwable $e) {
            $this->flash('error', 'Nachtrag konnte nicht erstellt werden: ' . $e->getMessage());
            Response::redirect('/admin/auftrag/' . $order['public_id']);
        }
    }

    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $quote = $this->requireQuote($params['publicId'] ?? '');
        $customer = Db::run('SELECT * FROM customers WHERE id = ? LIMIT 1', [(int) $quote['customer_id']])->fetch();
        $pdfPublic = null;
        if ($quote['pdf_asset_id'] !== null) {
            $pdfPublic = Db::run('SELECT public_id FROM assets WHERE id = ? LIMIT 1', [(int) $quote['pdf_asset_id']])->fetchColumn() ?: null;
        }

        Response::html(View::render('admin/quotes/show', [
            'title'      => 'Angebot ' . ($quote['quote_number'] ?? '(Entwurf)'),
            'nav'        => 'quotes',
            'quote'      => $quote,
            'customer'   => $customer,
            'items'      => QuoteRepo::items((int) $quote['id']),
            'pdf_public' => $pdfPublic,
            'flash'      => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function send(array $params): void
    {
        $quote = $this->requireQuote($params['publicId'] ?? '');
        try {
            $sent = QuoteService::send((int) $quote['id'], Auth::id());
            $base = rtrim((string) (Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
            $link = $base . '/angebot/' . $quote['public_id'] . '?t=' . $sent['token'];
            $this->flash('ok', 'Angebot ' . $sent['quote_number'] . ' versendet (Mail in der Outbox). Kundenlink: ' . $link);
        } catch (\Throwable $e) {
            $this->flash('error', 'Versand fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/angebot/' . $quote['public_id']);
    }

    /**
     * @param string $publicId
     * @return array<string,mixed>
     */
    private function requireQuote(string $publicId): array
    {
        $quote = QuoteRepo::findByPublicId($publicId);
        if ($quote === null) {
            throw new HttpException(404, 'Angebot nicht gefunden.');
        }
        return $quote;
    }
}
