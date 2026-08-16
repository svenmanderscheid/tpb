<?php
declare(strict_types=1);

namespace Tpb\Domain\Quote;

/** Ungültiger/abgelaufener/widerrufener Angebotslink (Token). Führt zu 401/404 in der Kundenansicht. */
final class QuoteAccessException extends \RuntimeException
{
}
