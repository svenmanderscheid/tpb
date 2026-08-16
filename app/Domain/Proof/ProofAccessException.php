<?php
declare(strict_types=1);

namespace Tpb\Domain\Proof;

/** Ungültiger/abgelaufener/abgelöster Proof-Link (Token). Führt zu 404 in der Kundenansicht. */
final class ProofAccessException extends \RuntimeException
{
}
