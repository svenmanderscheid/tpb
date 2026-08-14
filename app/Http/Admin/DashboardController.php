<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Response;
use Tpb\Core\View;

final class DashboardController
{
    /** @param array<string,string> $params */
    public function root(array $params): void
    {
        Response::redirect(Auth::check() ? '/admin' : '/admin/login');
    }

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/dashboard', [
            'title'       => 'Dashboard',
            'displayName' => Auth::displayName() ?? 'Benutzer',
            'role'        => Auth::role() ?? '',
        ]));
    }
}
