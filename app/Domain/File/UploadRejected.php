<?php
declare(strict_types=1);

namespace Tpb\Domain\File;

/**
 * Wird geworfen, wenn ein Upload die Preflight-/Sicherheitsprüfung nicht besteht
 * (§5.4). Die Nachricht ist für die UI bestimmt (keine internen Details).
 */
final class UploadRejected extends \RuntimeException
{
}
