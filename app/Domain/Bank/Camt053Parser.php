<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

/**
 * CAMT.053 (ISO 20022 bank-to-customer statement, §5.6). Liest die Buchungseinträge
 * (<Ntry>): Betrag + Vorzeichen (CdtDbtInd), Buchungs-/Valutadatum, Verwendungszweck,
 * EndToEndId, Gegenpartei + IBAN. Namespaces werden ignoriert (lokale Elementnamen).
 *
 * @return array<int,array<string,mixed>>
 */
final class Camt053Parser
{
    /** @return array<int,array<string,mixed>> */
    public static function parse(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        // Namespaces entfernen, damit einfache XPath/Property-Zugriffe funktionieren.
        $clean = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $xml) ?? $xml;
        $doc = simplexml_load_string($clean);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            return [];
        }

        $entries = $doc->xpath('//Ntry') ?: [];
        $out = [];
        $lineNo = 0;
        foreach ($entries as $ntry) {
            $lineNo++;
            $amount = (float) ($ntry->Amt ?? 0);
            $currency = strtoupper((string) ($ntry->Amt['Ccy'] ?? 'EUR')) ?: 'EUR';
            $ind = strtoupper((string) ($ntry->CdtDbtInd ?? 'CRDT'));
            $cents = (int) round($amount * 100);
            if ($ind === 'DBIT') {
                $cents = -abs($cents);
            }

            $txd = $ntry->NtryDtls->TxDtls ?? null;
            $remit = $txd !== null ? trim((string) ($txd->RmtInf->Ustrd ?? '')) : '';
            $e2e = $txd !== null ? trim((string) ($txd->Refs->EndToEndId ?? '')) : '';
            [$name, $iban] = self::counterparty($txd, $ind);

            $out[] = [
                'line_no'           => $lineNo,
                'booking_date'      => self::date((string) ($ntry->BookgDt->Dt ?? $ntry->BookgDt->DtTm ?? '')),
                'value_date'        => self::date((string) ($ntry->ValDt->Dt ?? $ntry->ValDt->DtTm ?? '')),
                'amount_cents'      => $cents,
                'currency'          => $currency,
                'counterparty_name' => $name !== '' ? mb_substr($name, 0, 160) : null,
                'counterparty_iban' => $iban !== '' ? mb_substr($iban, 0, 40) : null,
                'remittance_info'   => $remit !== '' ? mb_substr($remit, 0, 500) : null,
                'end_to_end_id'     => $e2e !== '' && strtoupper($e2e) !== 'NOTPROVIDED' ? mb_substr($e2e, 0, 80) : null,
            ];
        }
        return $out;
    }

    /** @return array{0:string,1:string} [name, iban] */
    private static function counterparty(?\SimpleXMLElement $txd, string $ind): array
    {
        if ($txd === null) {
            return ['', ''];
        }
        // Bei Eingang (CRDT) ist die Gegenpartei der Debitor, bei Ausgang der Kreditor.
        $party = $ind === 'DBIT' ? ($txd->RltdPties->Cdtr ?? null) : ($txd->RltdPties->Dbtr ?? null);
        $acct = $ind === 'DBIT' ? ($txd->RltdPties->CdtrAcct ?? null) : ($txd->RltdPties->DbtrAcct ?? null);
        $name = $party !== null ? trim((string) ($party->Nm ?? '')) : '';
        $iban = $acct !== null ? trim((string) ($acct->Id->IBAN ?? '')) : '';
        return [$name, $iban];
    }

    private static function date(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $t, $m) ? $m[1] : null;
    }
}
