<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\HttpException;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Invoice\InvoiceRepo;
use Tpb\Domain\Invoice\InvoiceService;
use Tpb\Domain\Invoice\InvoiceStateException;

/**
 * Backoffice: Rechnungen & Gutschriften (M6). Ausstellen/Gutschrift: tpb_issue_invoices
 * (owner/finance); Lesen: tpb_view_costs. ISSUED ist unumkehrbar (§11.2).
 */
final class InvoiceController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/invoices/index', [
            'title'    => 'Rechnungen',
            'nav'      => 'invoices',
            'invoices' => InvoiceRepo::listForAdmin(),
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $inv = $this->requireInvoice($params['publicId'] ?? '');
        $pdfPublic = null;
        if ($inv['pdf_asset_id'] !== null) {
            $pdfPublic = Db::run('SELECT public_id FROM assets WHERE id = ? LIMIT 1', [(int) $inv['pdf_asset_id']])->fetchColumn() ?: null;
        }
        Response::html(View::render('admin/invoices/show', [
            'title'    => ($inv['doc_type'] === 'credit_note' ? 'Gutschrift ' : 'Rechnung ') . ($inv['invoice_number'] ?? '(Entwurf)'),
            'nav'      => 'invoices',
            'invoice'  => $inv,
            'lines'    => InvoiceRepo::lines((int) $inv['id']),
            'credits'  => InvoiceRepo::creditNotesFor((int) $inv['id']),
            'pdf_public' => $pdfPublic,
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** Rechnungsentwurf aus einem Auftrag. @param array<string,string> $params */
    public function createFromOrder(array $params): void
    {
        $order = Db::run('SELECT id, public_id FROM orders WHERE public_id = ? LIMIT 1', [$params['publicId'] ?? ''])->fetch();
        if ($order === false) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        try {
            $inv = InvoiceService::createDraft((int) $order['id'], Auth::id());
            $this->flash('ok', 'Rechnungsentwurf erstellt. Bitte prüfen und ausstellen.');
            Response::redirect('/admin/rechnung/' . $inv['public_id']);
            return;
        } catch (\Throwable $e) {
            $this->flash('error', 'Entwurf fehlgeschlagen: ' . $e->getMessage());
            Response::redirect('/admin/auftrag/' . $order['public_id']);
        }
    }

    /** @param array<string,string> $params */
    public function issue(array $params): void
    {
        $inv = $this->requireInvoice($params['publicId'] ?? '');
        try {
            $res = InvoiceService::issue((int) $inv['id'], Auth::id());
            $this->flash('ok', 'Rechnung ' . $res['invoice_number'] . ' ausgestellt (unumkehrbar).');
        } catch (InvoiceStateException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('error', 'Ausstellung fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/rechnung/' . $inv['public_id']);
    }

    /** @param array<string,string> $params */
    public function credit(array $params): void
    {
        $inv = $this->requireInvoice($params['publicId'] ?? '');
        try {
            $res = InvoiceService::creditNote((int) $inv['id'], Auth::id());
            $this->flash('ok', 'Gutschrift ' . $res['invoice_number'] . ' erstellt und ausgestellt.');
            Response::redirect('/admin/rechnung/' . $res['credit_public_id']);
            return;
        } catch (InvoiceStateException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash('error', 'Gutschrift fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/rechnung/' . $inv['public_id']);
    }

    /**
     * @param string $publicId
     * @return array<string,mixed>
     */
    private function requireInvoice(string $publicId): array
    {
        $inv = InvoiceRepo::findByPublicId($publicId);
        if ($inv === null) {
            throw new HttpException(404, 'Rechnung nicht gefunden.');
        }
        return $inv;
    }
}
