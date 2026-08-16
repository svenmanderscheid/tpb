<?php
declare(strict_types=1);

namespace Tpb\Domain\Shop;

/** Checkout nicht möglich (Konfiguration ungültig, Mindestbestellwert, Zustimmungen fehlen). */
final class CheckoutException extends \RuntimeException
{
}
