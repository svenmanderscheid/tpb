<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Catalog\TechniqueRepo;

/**
 * Admin-CRUD Techniken (M1). Rechte: tpb_manage_pricing. Mutation: CSRF + Audit.
 */
final class TechniqueController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/catalog/techniques', [
            'title'      => 'Techniken',
            'nav'        => 'catalog',
            'techniques' => TechniqueRepo::all(),
            'flash'      => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function store(array $params): void
    {
        try {
            $code = strtoupper(trim((string) Request::post('code', '')));
            $name = trim((string) Request::post('name', ''));
            if (!preg_match('/^[A-Z0-9_]{2,32}$/', $code)) {
                throw new \InvalidArgumentException('Code: 2–32 Zeichen A–Z, 0–9, _.');
            }
            if ($name === '') {
                throw new \InvalidArgumentException('Name ist Pflicht.');
            }
            if (TechniqueRepo::codeExists($code)) {
                throw new \InvalidArgumentException('Code existiert bereits.');
            }
            TechniqueRepo::create($code, mb_substr($name, 0, 80));
            Audit::log('technique', $code, 'catalog.technique.created', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', 'Technik angelegt.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/techniken');
    }

    /** @param array<string,string> $params */
    public function update(array $params): void
    {
        $code = strtoupper((string) ($params['code'] ?? ''));
        try {
            if (!TechniqueRepo::codeExists($code)) {
                throw new \InvalidArgumentException('Technik nicht gefunden.');
            }
            $name = trim((string) Request::post('name', ''));
            $status = Request::post('status') === 'inactive' ? 'inactive' : 'active';
            if ($name === '') {
                throw new \InvalidArgumentException('Name ist Pflicht.');
            }
            TechniqueRepo::update($code, mb_substr($name, 0, 80), $status);
            Audit::log('technique', $code, 'catalog.technique.updated', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', 'Technik gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/techniken');
    }
}
