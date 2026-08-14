<?php
declare(strict_types=1);

use Tpb\Http\Admin\AssetController;
use Tpb\Http\Admin\AuthController;
use Tpb\Http\Admin\DashboardController;
use Tpb\Http\Admin\FileController;

/**
 * Routen-Tabelle (§4): [METHOD, PATTERN, [Controller, 'action'], [middleware-tags]].
 * Middleware-Tags: public, auth[:<capability>], csrf, rate:<bucket>.
 */
return [
    ['GET',  '/',              [DashboardController::class, 'root'],  ['public']],

    // Auth
    ['GET',  '/admin/login',   [AuthController::class, 'showLogin'],  ['public']],
    ['POST', '/admin/login',   [AuthController::class, 'login'],      ['public', 'csrf']],
    ['POST', '/admin/logout',  [AuthController::class, 'logout'],     ['auth', 'csrf']],

    // Backoffice
    ['GET',  '/admin',         [DashboardController::class, 'index'], ['auth']],

    // Assets (M0: Upload in Quarantäne + Preflight, §8)
    ['GET',  '/admin/assets',           [AssetController::class, 'index'],  ['auth:tpb_manage_artwork']],
    ['POST', '/admin/assets',           [AssetController::class, 'upload'], ['auth:tpb_manage_artwork', 'csrf', 'rate:upload']],

    // Gesicherter Download (§8): Auth/Objektberechtigung oder kurzlebiges Token
    ['GET',  '/files/{publicId}',       [FileController::class, 'download'], ['public']],
];
