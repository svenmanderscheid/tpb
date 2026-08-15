<?php
declare(strict_types=1);

use Tpb\Http\Admin\AssetController;
use Tpb\Http\Admin\AuthController;
use Tpb\Http\Admin\CatalogController;
use Tpb\Http\Admin\DashboardController;
use Tpb\Http\Admin\FileController;
use Tpb\Http\Admin\CostVersionController;
use Tpb\Http\Admin\FinanceController;
use Tpb\Http\Admin\PriceBookController;
use Tpb\Http\Admin\TechniqueController;

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

    // Finanzen (Abweichung, docs/DECISIONS.md #14-18) – Lesen: tpb_view_costs, Schreiben: tpb_manage_finance
    ['GET',  '/admin/finanzen',                       [FinanceController::class, 'dashboard'],          ['auth:tpb_view_costs']],
    ['GET',  '/admin/finanzen/buchungen',             [FinanceController::class, 'entries'],            ['auth:tpb_view_costs']],
    ['POST', '/admin/finanzen/buchungen',             [FinanceController::class, 'storeEntry'],         ['auth:tpb_manage_finance', 'csrf']],
    ['POST', '/admin/finanzen/buchungen/loeschen',    [FinanceController::class, 'deleteEntry'],        ['auth:tpb_manage_finance', 'csrf']],
    ['GET',  '/admin/finanzen/gesellschafter',        [FinanceController::class, 'partners'],           ['auth:tpb_view_costs']],
    ['POST', '/admin/finanzen/gesellschafter',        [FinanceController::class, 'storePartner'],       ['auth:tpb_manage_finance', 'csrf']],
    ['POST', '/admin/finanzen/einlagen',              [FinanceController::class, 'storeContribution'],  ['auth:tpb_manage_finance', 'csrf']],
    ['POST', '/admin/finanzen/einlagen/loeschen',     [FinanceController::class, 'deleteContribution'], ['auth:tpb_manage_finance', 'csrf']],

    // Katalog (M1) – Rechte: tpb_manage_pricing (owner/admin)
    ['GET',  '/admin/katalog',                                       [CatalogController::class, 'products'],      ['auth:tpb_manage_pricing']],
    ['POST', '/admin/katalog',                                       [CatalogController::class, 'storeProduct'],  ['auth:tpb_manage_pricing', 'csrf']],
    ['GET',  '/admin/katalog/produkt/{publicId}',                    [CatalogController::class, 'product'],        ['auth:tpb_manage_pricing']],
    ['POST', '/admin/katalog/produkt/{publicId}',                    [CatalogController::class, 'updateProduct'],  ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/katalog/produkt/{publicId}/varianten',          [CatalogController::class, 'addVariant'],     ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/katalog/produkt/{publicId}/varianten/loeschen', [CatalogController::class, 'deleteVariant'],  ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/katalog/produkt/{publicId}/placements',         [CatalogController::class, 'addPlacement'],   ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/katalog/produkt/{publicId}/placements/loeschen',[CatalogController::class, 'deletePlacement'],['auth:tpb_manage_pricing', 'csrf']],
    ['GET',  '/admin/techniken',                                     [TechniqueController::class, 'index'],        ['auth:tpb_manage_pricing']],
    ['POST', '/admin/techniken',                                     [TechniqueController::class, 'store'],        ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/techniken/{code}',                              [TechniqueController::class, 'update'],       ['auth:tpb_manage_pricing', 'csrf']],

    // Preisbücher (M1) mit Draft->Publish
    ['GET',  '/admin/preisbuecher',                    [PriceBookController::class, 'index'],      ['auth:tpb_manage_pricing']],
    ['POST', '/admin/preisbuecher',                    [PriceBookController::class, 'store'],      ['auth:tpb_manage_pricing', 'csrf']],
    ['GET',  '/admin/preisbuch/{version}',             [PriceBookController::class, 'show'],       ['auth:tpb_manage_pricing']],
    ['POST', '/admin/preisbuch/{version}/tier',        [PriceBookController::class, 'addTier'],    ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/preisbuch/{version}/tier/loeschen', [PriceBookController::class, 'deleteTier'], ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/preisbuch/{version}/param',       [PriceBookController::class, 'setParam'],   ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/preisbuch/{version}/param/loeschen', [PriceBookController::class, 'deleteParam'], ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/preisbuch/{version}/publish',     [PriceBookController::class, 'publish'],    ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/preisbuch/{version}/retire',      [PriceBookController::class, 'retire'],     ['auth:tpb_manage_pricing', 'csrf']],

    // Kostenversionen (M1) mit Draft->Publish
    ['GET',  '/admin/kostenversionen',                 [CostVersionController::class, 'index'],    ['auth:tpb_manage_pricing']],
    ['POST', '/admin/kostenversionen',                 [CostVersionController::class, 'store'],    ['auth:tpb_manage_pricing', 'csrf']],
    ['GET',  '/admin/kostenversion/{version}',         [CostVersionController::class, 'show'],     ['auth:tpb_manage_pricing']],
    ['POST', '/admin/kostenversion/{version}/saetze',  [CostVersionController::class, 'updateRates'], ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/kostenversion/{version}/item',    [CostVersionController::class, 'addItem'],   ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/kostenversion/{version}/item/loeschen', [CostVersionController::class, 'deleteItem'], ['auth:tpb_manage_pricing', 'csrf']],
    ['POST', '/admin/kostenversion/{version}/publish', [CostVersionController::class, 'publish'],   ['auth:tpb_manage_pricing', 'csrf']],
];
