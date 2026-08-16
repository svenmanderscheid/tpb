<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\Ulid;
use Tpb\Core\View;
use Tpb\Domain\Label\LabelService;
use Tpb\Domain\Production\JobRepo;
use Tpb\Domain\Production\ProductionGateException;
use Tpb\Domain\Production\ProductionService;
use Tpb\Domain\Production\ProductionStateException;

/**
 * Backoffice: Produktionswarteschlange und Job-Steuerung (M5).
 * Recht: tpb_manage_production (owner/admin/production).
 */
final class ProductionController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function queue(array $params): void
    {
        Response::html(View::render('admin/production/queue', [
            'title' => 'Produktion',
            'nav'   => 'production',
            'jobs'  => JobRepo::queue(),
            'flash' => $this->takeFlash(),
        ]));
    }

    /** Legt aus einem Auftrag die Produktionsjobs an. @param array<string,string> $params */
    public function createForOrder(array $params): void
    {
        $order = Db::run('SELECT id, public_id FROM orders WHERE public_id = ? LIMIT 1', [$params['publicId'] ?? ''])->fetch();
        if ($order === false) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        try {
            $jobs = ProductionService::createJobs((int) $order['id'], Auth::id());
            $this->flash('ok', $jobs === [] ? 'Es bestehen bereits Jobs für diesen Auftrag.' : count($jobs) . ' Job(s) angelegt.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Jobs konnten nicht angelegt werden: ' . $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /** @param array<string,string> $params */
    public function job(array $params): void
    {
        $job = $this->requireJob($params['publicId'] ?? '');
        $order = Db::run('SELECT order_number, public_id, cur_artwork, cur_payment FROM orders WHERE id = ? LIMIT 1', [(int) $job['order_id']])->fetch();
        $item = Db::run('SELECT description, qty FROM order_items WHERE id = ? LIMIT 1', [(int) $job['order_item_id']])->fetch();
        $lastPrint = Db::run("SELECT id FROM print_jobs WHERE entity_type='production_job' AND entity_id=? ORDER BY id DESC LIMIT 1", [(int) $job['id']])->fetchColumn();

        Response::html(View::render('admin/production/job', [
            'title'      => 'Job ' . $job['job_number'],
            'nav'        => 'production',
            'job'        => $job,
            'order'      => $order,
            'item'       => $item,
            'events'     => JobRepo::events((int) $job['id']),
            'idem_key'   => Ulid::generate(),
            'last_print' => $lastPrint !== false ? (int) $lastPrint : null,
            'flash'      => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function release(array $params): void
    {
        $job = $this->requireJob($params['publicId'] ?? '');
        try {
            ProductionService::release((string) $job['public_id'], Auth::id());
            $this->flash('ok', 'Job freigegeben (READY).');
        } catch (ProductionGateException | ProductionStateException $e) {
            $this->flash('error', 'Freigabe nicht möglich: ' . $e->getMessage());
        }
        Response::redirect('/admin/job/' . $job['public_id']);
    }

    /** @param array<string,string> $params */
    public function advance(array $params): void
    {
        $job = $this->requireJob($params['publicId'] ?? '');
        try {
            ProductionService::advance((string) $job['public_id'], (string) Request::post('action', ''), Auth::id());
            $this->flash('ok', 'Status aktualisiert.');
        } catch (ProductionStateException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/job/' . $job['public_id']);
    }

    /** @param array<string,string> $params */
    public function quantity(array $params): void
    {
        $job = $this->requireJob($params['publicId'] ?? '');
        try {
            ProductionService::recordQuantity(
                (string) $job['public_id'],
                (string) Request::post('type', ''),
                (int) Request::post('qty', '0'),
                (string) Request::post('idem_key', ''),
                Auth::id()
            );
            $this->flash('ok', 'Menge erfasst.');
        } catch (ProductionStateException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/job/' . $job['public_id']);
    }

    /** @param array<string,string> $params */
    public function label(array $params): void
    {
        $job = $this->requireJob($params['publicId'] ?? '');
        try {
            $res = LabelService::render((string) $job['public_id'], Auth::id());
            $this->flash('ok', 'Etikett gerendert (print_job #' . $res['print_job_id'] . '). PDF: /files/' . $res['asset_public_id']);
        } catch (\Throwable $e) {
            $this->flash('error', 'Etikett fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/job/' . $job['public_id']);
    }

    /** @param array<string,string> $params */
    public function reprint(array $params): void
    {
        $job = $this->requireJob($params['publicId'] ?? '');
        $reason = trim((string) Request::post('reason', ''));
        $reprintOf = (int) Request::post('reprint_of', '0');
        try {
            if ($reason === '') {
                throw new \InvalidArgumentException('Bitte einen Grund für den Neudruck angeben.');
            }
            $res = LabelService::render((string) $job['public_id'], Auth::id(), $reprintOf > 0 ? $reprintOf : null, $reason);
            $this->flash('ok', 'Neudruck erstellt (print_job #' . $res['print_job_id'] . ').');
        } catch (\Throwable $e) {
            $this->flash('error', 'Neudruck fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/job/' . $job['public_id']);
    }

    /**
     * @param string $publicId
     * @return array<string,mixed>
     */
    private function requireJob(string $publicId): array
    {
        $job = JobRepo::findByPublicId($publicId);
        if ($job === null) {
            throw new HttpException(404, 'Job nicht gefunden.');
        }
        return $job;
    }
}
