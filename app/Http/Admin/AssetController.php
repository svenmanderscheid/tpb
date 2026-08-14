<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\File\UploadRejected;

final class AssetController
{
    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $assets = Db::run(
            'SELECT public_id, original_name, mime, size_bytes, security_status, created_at
             FROM assets ORDER BY id DESC LIMIT 50'
        )->fetchAll();

        Response::html(View::render('admin/assets', [
            'title'  => 'Assets',
            'assets' => $assets,
            'flash'  => $flash,
        ]));
    }

    /** @param array<string,string> $params */
    public function upload(array $params): void
    {
        $file = $_FILES['file'] ?? null;
        try {
            if (!is_array($file)) {
                throw new UploadRejected('Es wurde keine Datei ausgewählt.');
            }
            /** @var array{name:string,type?:string,tmp_name:string,error:int,size:int} $file */
            $asset = AssetService::storeUpload($file, (int) Auth::id());
            $_SESSION['flash'] = [
                'type' => 'ok',
                'text' => 'Upload erfolgreich: ' . $asset['original_name'] . ' (Status: ' . $asset['security_status'] . ').',
            ];
        } catch (UploadRejected $e) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => $e->getMessage()];
        }
        Response::redirect('/admin/assets');
    }
}
