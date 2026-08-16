<?php
declare(strict_types=1);

namespace Tpb\Domain\Stock;

/** Nicht genügend Bestand für die gewünschte Menge (Checkout). Führt zu einer Kundenmeldung. */
final class StockException extends \RuntimeException
{
}
