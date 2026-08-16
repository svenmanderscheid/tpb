<?php
declare(strict_types=1);

namespace Tpb\Domain\Production;

/** Freigabe-Gate (§7.3) nicht erfüllt – der Job bleibt BLOCKED. Führt zu einer Flash-Meldung. */
final class ProductionGateException extends \RuntimeException
{
}
