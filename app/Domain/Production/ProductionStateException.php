<?php
declare(strict_types=1);

namespace Tpb\Domain\Production;

/** Aktion im aktuellen Job-Status nicht erlaubt (§7-Achse). Führt zu einer Flash-Meldung. */
final class ProductionStateException extends \RuntimeException
{
}
