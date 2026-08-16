<?php
declare(strict_types=1);

use Tpb\Http\Admin\AssetController;
use Tpb\Http\Admin\AuthController;
use Tpb\Http\Admin\CatalogController;
use Tpb\Http\Admin\DashboardController;
use Tpb\Http\Admin\FileController;
use Tpb\Http\Admin\CostVersionController;
use Tpb\Http\Admin\ExpenseController;
use Tpb\Http\Admin\FinanceController;
use Tpb\Http\Admin\InvoiceController;
use Tpb\Http\Admin\OrderController;
use Tpb\Http\Admin\PaymentController;
use Tpb\Http\Admin\PriceBookController;
use Tpb\Http\Admin\ProductionController;
use Tpb\Http\Admin\QuoteController;
use Tpb\Http\Admin\ShippingController;
use Tpb\Http\Admin\ShopController;
use Tpb\Http\Admin\TechniqueController;
use Tpb\Http\Api\ConfigController;
use Tpb\Http\Api\PriceController;
use Tpb\Http\Api\WebhookController;
use Tpb\Http\Site\CheckoutController;
use Tpb\Http\Site\ProofViewController;
use Tpb\Http\Site\QuoteViewController;
use Tpb\Http\Site\RequestController;
use Tpb\Http\Site\ScanController;
use Tpb\Http\Site\SiteController;

/**
 * Routen-Tabelle (§4): [METHOD, PATTERN, [Controller, 'action'], [middleware-tags]].
 * Middleware-Tags: public, auth[:<capability>], csrf, rate:<bucket>.
 */
return [
    // Öffentliche Basisseiten (M3)
    ['GET',  '/',                      [SiteController::class, 'home'],  ['public']],
    ['GET',  '/produkte',              [SiteController::class, 'index'], ['public']],
    ['GET',  '/rechtliches',           [SiteController::class, 'legal'], ['public']],
    ['GET',  '/rechtliches/{docType}', [SiteController::class, 'legal'], ['public']],

    // Öffentlicher Konfigurator (M2)
    ['GET',  '/konfigurator',                 [SiteController::class, 'index'],        ['public']],
    ['GET',  '/konfigurator/{publicId}',      [SiteController::class, 'configurator'], ['public']],

    // Angebotsanfrage (M3, Pfad A – Clubs/größere Bestellungen)
    ['GET',  '/anfrage',                       [RequestController::class, 'showForm'], ['public']],
    ['POST', '/anfrage',                       [RequestController::class, 'submit'],   ['public', 'csrf', 'rate:quote_request']],

    // Kundenansicht eines Angebots per Token (M3)
    ['GET',  '/angebot/{publicId}',            [QuoteViewController::class, 'show'],    ['public']],
    ['GET',  '/angebot/{publicId}/pdf',        [QuoteViewController::class, 'pdf'],     ['public']],
    ['POST', '/angebot/{publicId}/annehmen',   [QuoteViewController::class, 'accept'],  ['public', 'csrf']],
    ['POST', '/angebot/{publicId}/ablehnen',   [QuoteViewController::class, 'decline'], ['public', 'csrf']],

    // Kunden-Proof per Token (M4)
    ['GET',  '/proof/{publicId}',              [ProofViewController::class, 'show'],    ['public']],
    ['GET',  '/proof/{publicId}/pdf',          [ProofViewController::class, 'pdf'],     ['public']],
    ['POST', '/proof/{publicId}/freigeben',    [ProofViewController::class, 'approve'], ['public', 'csrf']],
    ['POST', '/proof/{publicId}/aenderung',    [ProofViewController::class, 'changes'], ['public', 'csrf']],

    // Interne Job-Scan-Ansicht per QR-Token (M5)
    ['GET',  '/scan/{publicId}',               [ScanController::class, 'show'],         ['public']],

    // Shop-Checkout (M6b, Pfad B) – Sofortkauf mit Online-Zahlung (Testmodus)
    ['GET',  '/checkout',                       [CheckoutController::class, 'form'],     ['public']],
    ['POST', '/checkout',                       [CheckoutController::class, 'submit'],   ['public', 'csrf', 'rate:price']],
    ['GET',  '/checkout/danke',                 [CheckoutController::class, 'thanks'],   ['public']],
    ['GET',  '/pay/{publicId}',                 [CheckoutController::class, 'pay'],      ['public']],
    ['POST', '/pay/{publicId}/simulieren',      [CheckoutController::class, 'simulate'], ['public', 'csrf']],
    ['POST', '/pay/{publicId}/abbrechen',       [CheckoutController::class, 'cancel'],   ['public', 'csrf']],
    ['POST', '/webhooks/payment',              [WebhookController::class, 'payment'],   ['public', 'rate:price']],

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

    // Öffentlicher Live-Preis (§6, M2) – serverseitige Berechnung, kein Client-Betrag
    ['POST', '/api/price',              [PriceController::class, 'quote'],   ['public', 'rate:price']],

    // Konfigurations-Entwurf (M2): speichern (mit Preis-Persistenz), laden, Gast-Upload
    ['POST', '/api/config/save',        [ConfigController::class, 'save'],   ['public', 'rate:price']],
    ['GET',  '/api/config/{publicId}',  [ConfigController::class, 'load'],   ['public']],
    ['POST', '/api/config/upload',      [ConfigController::class, 'upload'], ['public', 'rate:upload']],

    // Finanzen (Abweichung, docs/DECISIONS.md #14-18) – Lesen: tpb_view_costs, Schreiben: tpb_manage_finance
    ['GET',  '/admin/finanzen',                       [FinanceController::class, 'dashboard'],          ['auth:tpb_view_costs']],
    ['GET',  '/admin/finanzen/buchungen',             [FinanceController::class, 'entries'],            ['auth:tpb_view_costs']],
    ['POST', '/admin/finanzen/buchungen',             [FinanceController::class, 'storeEntry'],         ['auth:tpb_manage_finance', 'csrf']],
    ['POST', '/admin/finanzen/buchungen/loeschen',    [FinanceController::class, 'deleteEntry'],        ['auth:tpb_manage_finance', 'csrf']],
    ['GET',  '/admin/finanzen/gesellschafter',        [FinanceController::class, 'partners'],           ['auth:tpb_view_costs']],
    ['POST', '/admin/finanzen/gesellschafter',        [FinanceController::class, 'storePartner'],       ['auth:tpb_manage_finance', 'csrf']],
    ['POST', '/admin/finanzen/einlagen',              [FinanceController::class, 'storeContribution'],  ['auth:tpb_manage_finance', 'csrf']],
    ['POST', '/admin/finanzen/einlagen/loeschen',     [FinanceController::class, 'deleteContribution'], ['auth:tpb_manage_finance', 'csrf']],

    // Angebote (M3) – Rechte: tpb_manage_quotes (owner/admin/sales)
    ['GET',  '/admin/anfragen',                          [QuoteController::class, 'index'],       ['auth:tpb_manage_quotes']],
    ['GET',  '/admin/angebote',                          [QuoteController::class, 'index'],       ['auth:tpb_manage_quotes']],
    ['POST', '/admin/anfragen/{publicId}/angebot',       [QuoteController::class, 'createQuote'], ['auth:tpb_manage_quotes', 'csrf']],
    ['GET',  '/admin/angebot/{publicId}',                [QuoteController::class, 'show'],        ['auth:tpb_manage_quotes']],
    ['POST', '/admin/angebot/{publicId}/versenden',      [QuoteController::class, 'send'],        ['auth:tpb_manage_quotes', 'csrf']],

    // Aufträge & Proof (M4) – Rechte: tpb_manage_artwork (owner/admin/sales)
    ['GET',  '/admin/auftraege',                         [OrderController::class, 'index'],         ['auth:tpb_manage_artwork']],
    ['GET',  '/admin/auftrag/{publicId}',                [OrderController::class, 'show'],          ['auth:tpb_manage_artwork']],
    ['POST', '/admin/auftrag/{publicId}/artwork',        [OrderController::class, 'uploadArtwork'], ['auth:tpb_manage_artwork', 'csrf', 'rate:upload']],
    ['POST', '/admin/auftrag/{publicId}/proof',          [OrderController::class, 'createProof'],   ['auth:tpb_manage_artwork', 'csrf']],

    // Produktion & Etikett (M5) – Rechte: tpb_manage_production (owner/admin/production)
    ['GET',  '/admin/produktion',                        [ProductionController::class, 'queue'],         ['auth:tpb_manage_production']],
    ['POST', '/admin/auftrag/{publicId}/produktion',     [ProductionController::class, 'createForOrder'], ['auth:tpb_manage_production', 'csrf']],
    ['GET',  '/admin/job/{publicId}',                    [ProductionController::class, 'job'],            ['auth:tpb_manage_production']],
    ['POST', '/admin/job/{publicId}/freigeben',          [ProductionController::class, 'release'],        ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/job/{publicId}/aktion',             [ProductionController::class, 'advance'],        ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/job/{publicId}/menge',              [ProductionController::class, 'quantity'],       ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/job/{publicId}/etikett',            [ProductionController::class, 'label'],          ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/job/{publicId}/etikett/neu',        [ProductionController::class, 'reprint'],        ['auth:tpb_manage_production', 'csrf']],

    // Rechnungen & Gutschriften (M6) – Ausstellen: tpb_issue_invoices, Lesen: tpb_view_costs
    ['GET',  '/admin/rechnungen',                        [InvoiceController::class, 'index'],          ['auth:tpb_view_costs']],
    ['GET',  '/admin/rechnung/{publicId}',               [InvoiceController::class, 'show'],           ['auth:tpb_view_costs']],
    ['POST', '/admin/auftrag/{publicId}/rechnung',       [InvoiceController::class, 'createFromOrder'], ['auth:tpb_issue_invoices', 'csrf']],
    ['POST', '/admin/rechnung/{publicId}/ausstellen',    [InvoiceController::class, 'issue'],           ['auth:tpb_issue_invoices', 'csrf']],
    ['POST', '/admin/rechnung/{publicId}/gutschrift',    [InvoiceController::class, 'credit'],          ['auth:tpb_issue_invoices', 'csrf']],

    // Zahlungen (M6) – Recht: tpb_manage_finance
    ['POST', '/admin/auftrag/{publicId}/zahlung',        [PaymentController::class, 'record'],          ['auth:tpb_manage_finance', 'csrf']],

    // Ausgaben (M6) – Lesen: tpb_view_costs, Erfassen: tpb_manage_finance
    ['GET',  '/admin/ausgaben',                          [ExpenseController::class, 'index'],           ['auth:tpb_view_costs']],
    ['POST', '/admin/ausgaben',                          [ExpenseController::class, 'store'],           ['auth:tpb_manage_finance', 'csrf', 'rate:upload']],

    // Shop-Zahlungen (M6b) – Lesen: tpb_view_costs
    ['GET',  '/admin/zahlungen',                         [ShopController::class, 'payments'],           ['auth:tpb_view_costs']],

    // Versand & Fulfillment (DECISIONS #28) – Rechte: tpb_manage_production
    ['POST', '/admin/auftrag/{publicId}/versand',              [ShippingController::class, 'configure'], ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/auftrag/{publicId}/versand/status',       [ShippingController::class, 'advance'],   ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/auftrag/{publicId}/versand/etikett',      [ShippingController::class, 'label'],     ['auth:tpb_manage_production', 'csrf']],
    ['POST', '/admin/auftrag/{publicId}/versand/etikett/neu',  [ShippingController::class, 'reprint'],   ['auth:tpb_manage_production', 'csrf']],

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
