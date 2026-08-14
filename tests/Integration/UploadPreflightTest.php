<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\File\PrivateStorage;
use Tpb\Domain\File\UploadRejected;

final class UploadPreflightTest extends TestCase
{
    private const TEST_UID = 999001;

    protected function setUp(): void
    {
        Env::set('UPLOAD_MAX_BYTES', '26214400');
        Db::run('DELETE FROM assets');
        Db::run('DELETE FROM audit_events');
        $this->cleanupStorage();
    }

    protected function tearDown(): void
    {
        $this->cleanupStorage();
    }

    public function testValidPngIsStoredCleanWithThumbnailAndAudit(): void
    {
        $file = $this->pngFile();
        $asset = AssetService::storeUpload($file, self::TEST_UID);

        self::assertSame('clean', $asset['security_status']);
        self::assertSame('image/png', $asset['mime']);
        self::assertTrue(PrivateStorage::exists("artwork/user-" . self::TEST_UID . "/{$asset['public_id']}/original"));
        self::assertNotNull($asset['thumb_key']);
        self::assertTrue(PrivateStorage::exists($asset['thumb_key']));

        $row = Db::run('SELECT security_status FROM assets WHERE public_id = ?', [$asset['public_id']])->fetch();
        self::assertSame('clean', $row['security_status']);

        // Audit bei Upload (DoD M0).
        $count = (int) Db::run(
            "SELECT COUNT(*) FROM audit_events WHERE aggregate_type='asset' AND event_type='asset.uploaded' AND aggregate_id=?",
            [$asset['public_id']]
        )->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testValidSvgAccepted(): void
    {
        $file = $this->tmpFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');
        $asset = AssetService::storeUpload($file, self::TEST_UID);
        self::assertSame('clean', $asset['security_status']);
        self::assertSame('image/svg+xml', $asset['mime']);
        self::assertNull($asset['thumb_key']); // Vektor: kein Raster-Thumbnail
    }

    public function testWrongExtensionRejected(): void
    {
        $file = $this->tmpFile('schaedlich.txt', 'nur text');
        $this->expectException(UploadRejected::class);
        AssetService::storeUpload($file, self::TEST_UID);
    }

    public function testMimeExtensionMismatchRejected(): void
    {
        // PNG-Inhalt, aber als .pdf deklariert -> finfo image/png != application/pdf.
        $png = $this->pngBytes();
        $file = $this->tmpFile('getarnt.pdf', $png);
        $this->expectException(UploadRejected::class);
        AssetService::storeUpload($file, self::TEST_UID);
    }

    public function testOversizeRejected(): void
    {
        Env::set('UPLOAD_MAX_BYTES', '10');
        $file = $this->tmpFile('gross.png', $this->pngBytes());
        $this->expectException(UploadRejected::class);
        AssetService::storeUpload($file, self::TEST_UID);
    }

    public function testSvgWithScriptRejected(): void
    {
        $file = $this->tmpFile('boese.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->expectException(UploadRejected::class);
        AssetService::storeUpload($file, self::TEST_UID);
    }

    // -- Helfer ------------------------------------------------------------

    private function pngBytes(): string
    {
        $im = imagecreatetruecolor(40, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 120, 200));
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        return $bytes;
    }

    /** @return array{name:string,tmp_name:string,error:int,size:int} */
    private function pngFile(): array
    {
        return $this->tmpFile('logo.png', $this->pngBytes());
    }

    /** @return array{name:string,tmp_name:string,error:int,size:int} */
    private function tmpFile(string $name, string $contents): array
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'tpbtest');
        file_put_contents($tmp, $contents);
        return [
            'name'     => $name,
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($contents),
        ];
    }

    private function cleanupStorage(): void
    {
        $dir = PrivateStorage::path('artwork/user-' . self::TEST_UID);
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
