<?php
declare(strict_types=1);

namespace Tpb\Domain\Legal;

use Tpb\Core\Db;

/**
 * Versionierte Rechtstexte (§5.4, legal_document_versions). M3 nutzt die jeweils
 * veröffentlichte Fassung je doc_type/Sprache für Consent + order_terms_acceptance.
 * Inhalte sind bis zur juristischen Freigabe markierte Platzhalter (DECISIONS #25).
 */
final class LegalDocRepo
{
    /** @return array<string,mixed>|null */
    public static function published(string $docType, string $lang = 'de'): ?array
    {
        $row = Db::run(
            "SELECT * FROM legal_document_versions
             WHERE doc_type = ? AND language = ? AND status = 'published'
             ORDER BY id DESC LIMIT 1",
            [$docType, $lang]
        )->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function byId(int $id): ?array
    {
        $row = Db::run('SELECT * FROM legal_document_versions WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Alle aktuell veröffentlichten Rechtstexte je Sprache (für die Consent-Anzeige).
     * @return array<int,array<string,mixed>>
     */
    public static function allPublished(string $lang = 'de'): array
    {
        return Db::run(
            "SELECT * FROM legal_document_versions
             WHERE language = ? AND status = 'published'
             ORDER BY doc_type ASC, id DESC",
            [$lang]
        )->fetchAll();
    }
}
