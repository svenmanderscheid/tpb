<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

/**
 * Fehler in der Preisberechnung (§6), z. B. fehlender Staffelpreis –
 * niemals 0 annehmen.
 */
final class PricingException extends \RuntimeException
{
}
