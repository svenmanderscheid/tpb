<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

/** Ungültige Versandaktion im aktuellen Zustand (Fulfillment-Achse §7). Führt zu einer Flash-Meldung. */
final class ShippingStateException extends \RuntimeException
{
}
