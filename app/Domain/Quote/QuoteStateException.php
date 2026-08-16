<?php
declare(strict_types=1);

namespace Tpb\Domain\Quote;

/** Angebot im falschen Status für die verlangte Aktion (z. B. abgelaufen/bereits abgelehnt). Führt zu 409. */
final class QuoteStateException extends \RuntimeException
{
}
