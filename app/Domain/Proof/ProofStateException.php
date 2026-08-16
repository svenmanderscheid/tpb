<?php
declare(strict_types=1);

namespace Tpb\Domain\Proof;

/** Proof/Auftrag im falschen Status für die verlangte Aktion. Führt zu 409. */
final class ProofStateException extends \RuntimeException
{
}
