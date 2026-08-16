<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Expense\ExpenseRepo;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\File\UploadRejected;

/**
 * Backoffice: Ausgabenerfassung (§5.6, M6) mit optionalem Beleg-Upload und
 * optionaler Auftragszuordnung. Recht: tpb_manage_finance.
 */
final class ExpenseController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/expenses/index', [
            'title'    => 'Ausgaben',
            'nav'      => 'expenses',
            'expenses' => ExpenseRepo::listRecent(),
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function store(array $params): void
    {
        try {
            $vendor = trim((string) Request::post('vendor', ''));
            $category = trim((string) Request::post('category', ''));
            $amount = $this->eurosToCents((string) Request::post('amount', ''));
            $date = trim((string) Request::post('expense_date', ''));
            if ($vendor === '' || $category === '' || $amount <= 0 || $date === '') {
                throw new \InvalidArgumentException('Lieferant, Kategorie, Betrag und Datum sind Pflicht.');
            }

            $orderId = null;
            $orderNo = trim((string) Request::post('order_number', ''));
            if ($orderNo !== '') {
                $oid = Db::run('SELECT id FROM orders WHERE order_number = ? LIMIT 1', [$orderNo])->fetchColumn();
                if ($oid === false) {
                    throw new \InvalidArgumentException('Auftragsnummer nicht gefunden.');
                }
                $orderId = (int) $oid;
            }

            $receiptAssetId = null;
            $file = $_FILES['receipt'] ?? null;
            if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                /** @var array{name:string,type?:string,tmp_name:string,error:int,size:int} $file */
                $asset = AssetService::storeUpload($file, Auth::id() ?? 0, 'receipt', 'invoice_10y');
                $receiptAssetId = (int) $asset['id'];
            }

            ExpenseRepo::create([
                'expense_date' => $date, 'vendor' => $vendor, 'category' => $category,
                'description' => trim((string) Request::post('description', '')),
                'amount_cents' => $amount, 'order_id' => $orderId, 'receipt_asset_id' => $receiptAssetId,
            ], Auth::id());
            $this->flash('ok', 'Ausgabe erfasst.');
        } catch (UploadRejected $e) {
            $this->flash('error', 'Beleg abgelehnt: ' . $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/ausgaben');
    }

    private function eurosToCents(string $raw): int
    {
        $t = str_replace([' ', "\u{00a0}", ','], ['', '', '.'], trim($raw));
        return $t === '' || !is_numeric($t) ? 0 : (int) round((float) $t * 100);
    }
}
