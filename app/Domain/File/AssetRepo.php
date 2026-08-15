<?php
declare(strict_types=1);

namespace Tpb\Domain\File;

use Tpb\Core\Db;

/**
 * Lesezugriffe auf Assets (öffentliche Auflösung public_id → interne Daten).
 */
final class AssetRepo
{
    /** @return array{id:int,sha256:string,mime:string,security_status:string}|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run(
            'SELECT id, sha256, mime, security_status FROM assets WHERE public_id = ? LIMIT 1',
            [$publicId]
        )->fetch();
        if (!$row) {
            return null;
        }
        return [
            'id'              => (int) $row['id'],
            'sha256'          => (string) $row['sha256'],
            'mime'            => (string) $row['mime'],
            'security_status' => (string) $row['security_status'],
        ];
    }
}
