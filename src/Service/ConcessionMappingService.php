<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service for mapping WhatsApp phone_number_id to concession codes
 */
class ConcessionMappingService
{
    /**
     * Mapping of phone_number_id to concession code
     * Key: Meta phone_number_id
     * Value: Concession code (CN = Costanera Norte, VS = Vespucio Sur)
     */
    private const PHONE_NUMBER_MAPPING = [
        '651420641396348' => 'CN',   // Costanera Norte / Grupo Costanera
        '888885257633217' => 'VS',   // Vespucio Sur / Craetion fallback
    ];

    /**
     * Concession display names
     */
    private const CONCESSION_NAMES = [
        'CN' => 'Costanera Norte',
        'VS' => 'Vespucio Sur',
    ];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Gets the concession code for a given phone_number_id
     *
     * @return string|null Returns null if phone_number_id is not mapped (logs warning)
     */
    public function getConcessionByPhoneNumberId(string $phoneNumberId): ?string
    {
        if (isset(self::PHONE_NUMBER_MAPPING[$phoneNumberId])) {
            return self::PHONE_NUMBER_MAPPING[$phoneNumberId];
        }

        $this->logger->warning('Unknown phone_number_id received', [
            'phone_number_id' => $phoneNumberId,
            'known_ids' => array_keys(self::PHONE_NUMBER_MAPPING)
        ]);

        return null;
    }

    /**
     * Gets all phone_number_ids for a given concession
     *
     * @return string[]
     */
    public function getPhoneNumberIdsByConcession(string $concessionCode): array
    {
        $phoneNumbers = [];
        foreach (self::PHONE_NUMBER_MAPPING as $phoneId => $code) {
            if ($code === $concessionCode) {
                $phoneNumbers[] = $phoneId;
            }
        }
        return $phoneNumbers;
    }

    /**
     * Gets all available concession codes
     *
     * @return string[]
     */
    public function getAvailableConcessions(): array
    {
        return array_unique(array_values(self::PHONE_NUMBER_MAPPING));
    }

    /**
     * Gets concession display name
     */
    public function getConcessionName(string $concessionCode): string
    {
        return self::CONCESSION_NAMES[$concessionCode] ?? $concessionCode;
    }

    /**
     * Gets all concessions with their display names
     *
     * @return array<string, string> [code => name]
     */
    public function getAllConcessionsWithNames(): array
    {
        return self::CONCESSION_NAMES;
    }

    /**
     * Checks if a phone_number_id is known
     */
    public function isKnownPhoneNumberId(string $phoneNumberId): bool
    {
        return isset(self::PHONE_NUMBER_MAPPING[$phoneNumberId]);
    }

    /**
     * Gets all phone number mappings
     *
     * @return array<string, string> [phone_number_id => concession_code]
     */
    public function getAllMappings(): array
    {
        return self::PHONE_NUMBER_MAPPING;
    }
}
