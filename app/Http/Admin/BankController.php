<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Bank\BankException;
use Tpb\Domain\Bank\BankImporter;
use Tpb\Domain\Bank\BankReconciliation;
use Tpb\Domain\Bank\BankRepo;
use Tpb\Domain\Bank\Matcher;

/**
 * Backoffice: Bankabgleich (§5.6, M8). Import (CSV/CAMT.053), Vorschläge und Bestätigung.
 * Es wird NIE automatisch gebucht – Recht: tpb_manage_finance.
 */
final class BankController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        $lines = [];
        foreach (BankRepo::openLines() as $line) {
            $line['suggestion'] = Matcher::suggest($line);
            $lines[] = $line;
        }
        Response::html(View::render('admin/bank/index', [
            'title'   => 'Bankabgleich',
            'nav'     => 'bank',
            'imports' => BankRepo::listImports(20),
            'lines'   => $lines,
            'flash'   => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function import(array $params): void
    {
        try {
            $file = $_FILES['statement'] ?? null;
            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new \InvalidArgumentException('Bitte eine Datei auswählen.');
            }
            $content = (string) file_get_contents((string) $file['tmp_name']);
            $format = Request::post('format') === 'camt053' ? 'camt053' : 'csv';
            $account = trim((string) Request::post('account_label', '')) ?: 'Hausbank';

            $res = BankImporter::import($account, $format, $content, Auth::id());
            if ($res['duplicate_file']) {
                $this->flash('error', 'Diese Datei wurde bereits importiert – keine neuen Zeilen.');
            } else {
                $this->flash('ok', "Import: {$res['parsed']} Zeilen gelesen, {$res['new']} neu übernommen.");
            }
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/bank');
    }

    /** @param array<string,string> $params */
    public function confirmPayment(array $params): void
    {
        $this->reconcile(function () use ($params): void {
            BankReconciliation::confirmPayment((int) ($params['id'] ?? 0), (int) Request::post('invoice_id', '0'), Auth::id());
            $this->flash('ok', 'Zahlung gebucht und zugeordnet.');
        });
    }

    /** @param array<string,string> $params */
    public function confirmExpense(array $params): void
    {
        $this->reconcile(function () use ($params): void {
            BankReconciliation::confirmExpense((int) ($params['id'] ?? 0), trim((string) Request::post('category', '')), Auth::id());
            $this->flash('ok', 'Ausgabe erfasst.');
        });
    }

    /** @param array<string,string> $params */
    public function ignore(array $params): void
    {
        BankReconciliation::ignore((int) ($params['id'] ?? 0), Auth::id());
        $this->flash('ok', 'Bankzeile ignoriert.');
        Response::redirect('/admin/bank');
    }

    private function reconcile(callable $fn): void
    {
        try {
            $fn();
        } catch (BankException | \InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/bank');
    }

    /** @param array<string,string> $params */
    private function requireLine(int $id): array
    {
        $line = BankRepo::lineById($id);
        if ($line === null) {
            throw new HttpException(404, 'Bankzeile nicht gefunden.');
        }
        return $line;
    }
}
