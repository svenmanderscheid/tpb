<?php
declare(strict_types=1);

namespace Tpb\Domain\Payment\Gateway;

/**
 * Abstraktion eines Online-Zahlungsanbieters (§5.7, Pfad B). Der echte Anbieter wird
 * später ein weiterer Adapter mit derselben Schnittstelle; die Entwicklung läuft
 * komplett im Testmodus (Owner 2026-08-16). Der Webhook verifiziert die Signatur
 * IMMER vor jeder Verarbeitung.
 */
interface PaymentGateway
{
    public function provider(): string;

    /**
     * Erstellt eine gehostete Checkout-Sitzung für einen Auftrag.
     * @return array{provider_ref:string,checkout_url:string}
     */
    public function createCheckout(string $intentPublicId, int $amountCents, string $currency): array;

    /** Verifiziert die Webhook-Signatur gegen den Rohtext (HMAC). */
    public function verifySignature(string $rawBody, string $signature): bool;
}
