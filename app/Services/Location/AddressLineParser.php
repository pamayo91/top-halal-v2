<?php

namespace App\Services\Location;

use Illuminate\Support\Str;

/**
 * Deterministic parsing for the V2 structured address contract.
 *
 * It deliberately refuses to arbitrate a disagreement between the historical
 * address and its separately stored postcode/city.
 */
class AddressLineParser
{
    /**
     * Returns the street part only when a complete, matching postcode/city
     * suffix is present. This is the strict rule used by the V2 repair.
     */
    public function repair(?string $addressLine1, ?string $rawAddress, ?string $postalCode, ?string $cityName): ?string
    {
        $line = $this->clean($addressLine1);
        $raw = $this->clean($rawAddress);

        if ($line === null || $raw === null || $line !== $raw) {
            return null;
        }

        return $this->stripMatchingSuffix($line, $postalCode, $cityName);
    }

    /**
     * Uses the historical first line only when it agrees with provider
     * structured data; otherwise falls back to an equally strict provider label.
     */
    public function fromHistoricalOrProvider(?string $historicalAddress, ?string $providerLabel, ?string $postalCode, ?string $cityName): ?string
    {
        $historical = $this->clean((string) strtok((string) $historicalAddress, ','));

        if ($historical !== null && preg_match('/\b\d{5}\b/u', $historical) !== 1) {
            return $historical;
        }

        return $this->stripMatchingSuffix($historical, $postalCode, $cityName)
            ?? $this->stripMatchingSuffix($this->clean($providerLabel), $postalCode, $cityName);
    }

    /** Parses a provider label against the postcode and city supplied with it. */
    public function fromProviderLabel(?string $label, ?string $postalCode, ?string $cityName): ?string
    {
        return $this->stripMatchingSuffix($this->clean($label), $postalCode, $cityName);
    }

    /** @return array{state:'candidate'|'ambiguous'|'ignored',new_line1:?string} */
    public function inspect(?string $addressLine1, ?string $rawAddress, ?string $postalCode, ?string $cityName): array
    {
        $candidate = $this->repair($addressLine1, $rawAddress, $postalCode, $cityName);
        if ($candidate !== null) {
            return ['state' => 'candidate', 'new_line1' => $candidate];
        }

        $line = $this->clean($addressLine1);
        $raw = $this->clean($rawAddress);
        $looksLikeCompleteHistoricalLine = $line !== null && $line === $raw && $postalCode && $cityName
            && preg_match('/\s+\d{5}\s+.+$/u', $line) === 1;

        return ['state' => $looksLikeCompleteHistoricalLine ? 'ambiguous' : 'ignored', 'new_line1' => null];
    }

    private function stripMatchingSuffix(?string $line, ?string $postalCode, ?string $cityName): ?string
    {
        $postalCode = trim((string) $postalCode);
        $cityName = trim((string) $cityName);

        if ($line === null || preg_match('/^\d{5}$/', $postalCode) !== 1 || $cityName === '') {
            return null;
        }

        if (preg_match('/^(?<street>.+?)\s+(?<postcode>\d{5})\s+(?<city>.+)$/u', $line, $parts) !== 1
            || $parts['postcode'] !== $postalCode
            || $this->normaliseCity($parts['city']) !== $this->normaliseCity($cityName)) {
            return null;
        }

        $street = $this->clean($parts['street']);

        return $street === null ? null : $street;
    }

    private function normaliseCity(string $city): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(Str::ascii(trim($city)))) ?? '';
    }

    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
