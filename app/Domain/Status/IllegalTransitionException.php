<?php
declare(strict_types=1);

namespace Tpb\Domain\Status;

/** Unerlaubter Statuswechsel (§7). Wird von States::assert geworfen. */
final class IllegalTransitionException extends \RuntimeException
{
}
