<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Finance\Amount;
use Tpb\Domain\Pricing\CostVersionRepo;

/**
 * Kostenversions-Verwaltung (M1) mit Draft→Publish. Nach 'published' unveränderlich.
 * Rechte: tpb_manage_pricing. TODO(M7): Publish erfordert Sudo-Modus (§11).
 */
final class CostVersionController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/pricing/cost_versions', [
            'title'       => 'Kostenversionen',
            'nav'         => 'pricing',
            'versions'    => CostVersionRepo::all(),
            'nextVersion' => CostVersionRepo::nextVersion(),
            'flash'       => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function store(array $params): void
    {
        try {
            $labor = Amount::toCents((string) Request::post('labor_rate', ''));
            $machine = Amount::toCents((string) Request::post('machine_rate', ''));
            $scrap = Amount::percentToBps((string) Request::post('scrap', '0'));
            $margin = Amount::percentToBps((string) Request::post('margin', '0'));
            $version = CostVersionRepo::nextVersion();
            CostVersionRepo::create($version, $labor, $machine, $scrap, $margin);
            Audit::log('cost_version', (string) $version, 'pricing.cost.created', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', "Kostenversion v{$version} (Entwurf) angelegt.");
            Response::redirect('/admin/kostenversion/' . $version);
            return;
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/kostenversionen');
    }

    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $cv = $this->requireVersion($params);
        Response::html(View::render('admin/pricing/cost_version', [
            'title'    => 'Kostenversion v' . $cv['version'],
            'nav'      => 'pricing',
            'cv'       => $cv,
            'items'    => CostVersionRepo::listItems((int) $cv['id']),
            'refTypes' => CostVersionRepo::REF_TYPES,
            'paramKeys' => CostVersionRepo::PARAM_KEYS,
            'isDraft'  => $cv['status'] === 'draft',
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function updateRates(array $params): void
    {
        $cv = $this->requireDraft($params);
        try {
            $labor = Amount::toCents((string) Request::post('labor_rate', ''));
            $machine = Amount::toCents((string) Request::post('machine_rate', ''));
            $scrap = Amount::percentToBps((string) Request::post('scrap', '0'));
            $margin = Amount::percentToBps((string) Request::post('margin', '0'));
            CostVersionRepo::updateRates((int) $cv['version'], $labor, $machine, $scrap, $margin);
            Audit::log('cost_version', (string) $cv['version'], 'pricing.cost.rates_updated', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', 'Sätze gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/kostenversion/' . $cv['version']);
    }

    /** @param array<string,string> $params */
    public function addItem(array $params): void
    {
        $cv = $this->requireDraft($params);
        try {
            $refType = (string) Request::post('ref_type', '');
            if (!in_array($refType, CostVersionRepo::REF_TYPES, true)) {
                throw new \InvalidArgumentException('Ungültiger Bezugstyp.');
            }
            $paramKey = strtoupper(trim((string) Request::post('param_key', '')));
            if (!in_array($paramKey, CostVersionRepo::PARAM_KEYS, true)) {
                throw new \InvalidArgumentException('Ungültiger Kostenschlüssel.');
            }
            $refId = (int) Request::post('ref_id', '0');
            if ($refId <= 0) {
                throw new \InvalidArgumentException('Bezugs-ID ist Pflicht.');
            }
            $value = (int) Request::post('value_int', '0');
            CostVersionRepo::addItem((int) $cv['id'], $refType, $refId, $paramKey, $value);
            Audit::log('cost_version', (string) $cv['version'], 'pricing.cost.item_set', ['actor_user_id' => Auth::id(), 'metadata' => ['ref' => "{$refType}:{$refId}:{$paramKey}", 'value' => $value]]);
            $this->flash('ok', 'Kostenposition gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/kostenversion/' . $cv['version']);
    }

    /** @param array<string,string> $params */
    public function deleteItem(array $params): void
    {
        $cv = $this->requireDraft($params);
        $id = (int) Request::post('id', '0');
        if ($id > 0) {
            CostVersionRepo::deleteItem($id, (int) $cv['id']);
            Audit::log('cost_version', (string) $cv['version'], 'pricing.cost.item_deleted', ['actor_user_id' => Auth::id(), 'metadata' => ['item_id' => $id]]);
            $this->flash('ok', 'Kostenposition gelöscht.');
        }
        Response::redirect('/admin/kostenversion/' . $cv['version']);
    }

    /** @param array<string,string> $params */
    public function publish(array $params): void
    {
        $cv = $this->requireVersion($params);
        if ($cv['status'] !== 'draft') {
            $this->flash('error', 'Nur Entwürfe können veröffentlicht werden.');
        } else {
            CostVersionRepo::publish((int) $cv['version'], Auth::id());
            Audit::log('cost_version', (string) $cv['version'], 'pricing.cost.published', ['actor_user_id' => Auth::id(), 'to_state' => 'published']);
            $this->flash('ok', "Kostenversion v{$cv['version']} veröffentlicht – jetzt unveränderlich.");
        }
        Response::redirect('/admin/kostenversion/' . $cv['version']);
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function requireVersion(array $params): array
    {
        $cv = CostVersionRepo::findByVersion((int) ($params['version'] ?? 0));
        if ($cv === null) {
            throw new HttpException(404, 'Kostenversion nicht gefunden.');
        }
        return $cv;
    }

    /**
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function requireDraft(array $params): array
    {
        $cv = $this->requireVersion($params);
        if ($cv['status'] !== 'draft') {
            throw new HttpException(403, 'Veröffentlichte Kostenversion ist unveränderlich.');
        }
        return $cv;
    }
}
