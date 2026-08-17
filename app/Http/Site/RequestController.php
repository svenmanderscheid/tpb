<?php
declare(strict_types=1);

namespace Tpb\Http\Site;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Customer\CustomerRepo;
use Tpb\Domain\Legal\LegalDocRepo;

/**
 * Öffentliche Angebotsanfrage (M3, Pfad A – Clubs/größere Bestellungen, DECISIONS #22).
 * Der Kunde bestätigt Kontaktdaten + versionierte Rechtserklärungen; die Konfiguration
 * wird auf 'submitted' gesetzt und einem Kunden zugeordnet. Kein Preis wird hier gesetzt.
 */
final class RequestController
{
    /** Pflicht-Zustimmungen für die Anfrage. */
    private const REQUIRED_DOCS = ['agb', 'widerruf'];

    /** @param array<string,string> $params */
    public function showForm(array $params): void
    {
        $configPublic = (string) (Request::query('config', '') ?? '');
        $config = $configPublic !== '' ? ConfigurationRepo::loadByPublicId($configPublic) : null;
        if ($config === null) {
            throw new HttpException(404, 'Zu dieser Anfrage wurde keine Konfiguration gefunden. Bitte zuerst im Konfigurator einen Entwurf speichern.');
        }

        Response::html(View::render('site/request_form', [
            'config'    => $config,
            'legalDocs' => LegalDocRepo::allPublished('de'),
            'required'  => self::REQUIRED_DOCS,
            'error'     => null,
            'old'       => [],
        ], 'layout/site'));
    }

    /** @param array<string,string> $params */
    public function submit(array $params): void
    {
        $configPublic = trim((string) Request::post('config', ''));
        $config = $configPublic !== '' ? ConfigurationRepo::loadByPublicId($configPublic) : null;
        if ($config === null) {
            throw new HttpException(404, 'Konfiguration nicht gefunden.');
        }

        $legalDocs = LegalDocRepo::allPublished('de');
        $old = [
            'type'         => (string) (Request::post('type', 'private') ?? 'private'),
            'company_name' => trim((string) Request::post('company_name', '')),
            'first_name'   => trim((string) Request::post('first_name', '')),
            'last_name'    => trim((string) Request::post('last_name', '')),
            'email'        => trim((string) Request::post('email', '')),
            'phone'        => trim((string) Request::post('phone', '')),
            'message'      => trim((string) Request::post('message', '')),
        ];

        $error = $this->validate($old);
        // Pflicht-Rechtstexte müssen angehakt sein (nur die, die auch veröffentlicht sind).
        $publishedTypes = array_column($legalDocs, 'doc_type');
        foreach (self::REQUIRED_DOCS as $docType) {
            if (in_array($docType, $publishedTypes, true) && Request::post('consent_' . $docType) !== '1') {
                $error = 'Bitte bestätigen Sie die erforderlichen Erklärungen (AGB und Widerrufsbelehrung).';
            }
        }

        if ($error !== null) {
            Response::html(View::render('site/request_form', [
                'config' => $config, 'legalDocs' => $legalDocs, 'required' => self::REQUIRED_DOCS,
                'error'  => $error, 'old' => $old,
            ], 'layout/site'), 422);
            return;
        }

        Db::tx(function () use ($config, $old, $legalDocs): void {
            $now = Clock::nowUtcSeconds();
            $customer = CustomerRepo::upsert([
                'type' => $old['type'], 'company_name' => $old['company_name'] !== '' ? $old['company_name'] : null,
                'first_name' => $old['first_name'], 'last_name' => $old['last_name'],
                'email' => $old['email'], 'phone' => $old['phone'] !== '' ? $old['phone'] : null,
            ]);

            $configId = (int) Db::run('SELECT id FROM configurations WHERE public_id = ? LIMIT 1', [$config['public_id']])->fetchColumn();
            Db::run(
                "UPDATE configurations SET customer_id = ?, guest_email = ?, guest_name = ?, note = ?, status = 'submitted', updated_at = ? WHERE id = ?",
                [$customer['id'], $old['email'], trim($old['first_name'] . ' ' . $old['last_name']), $old['message'] !== '' ? mb_substr($old['message'], 0, 500) : null, $now, $configId]
            );

            // Zustimmungen zu den zum Zeitpunkt veröffentlichten Rechtstexten festhalten.
            foreach ($legalDocs as $doc) {
                if (Request::post('consent_' . $doc['doc_type']) === '1') {
                    Db::run(
                        'INSERT INTO customer_consents (customer_id, purpose, legal_doc_version_id, granted_at, source, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [$customer['id'], (string) $doc['doc_type'], (int) $doc['id'], $now, 'angebotsanfrage', $now]
                    );
                }
            }

            Audit::log('configuration', (string) $config['public_id'], 'request.submitted', [
                'actor_label' => 'guest',
                'metadata'    => ['customer_id' => $customer['id'], 'email' => $old['email']],
            ]);
        });

        Response::html(View::render('site/request_thanks', ['name' => trim($old['first_name'] . ' ' . $old['last_name'])], 'layout/site'));
    }

    /** @param array<string,string> $d */
    private function validate(array $d): ?string
    {
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            return 'Bitte Vor- und Nachnamen angeben.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if ($d['type'] === 'business' && $d['company_name'] === '') {
            return 'Bitte den Firmennamen angeben.';
        }
        return null;
    }
}
