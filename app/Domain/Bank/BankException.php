<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

/** Ungültige Bankabgleich-Aktion (Zeile bereits bestätigt, falsches Vorzeichen …). */
final class BankException extends \RuntimeException
{
}
