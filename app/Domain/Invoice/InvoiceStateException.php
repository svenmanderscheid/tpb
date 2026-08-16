<?php
declare(strict_types=1);

namespace Tpb\Domain\Invoice;

/** Rechnung im falschen Status für die verlangte Aktion (z. B. ISSUED ist unumkehrbar). */
final class InvoiceStateException extends \RuntimeException
{
}
