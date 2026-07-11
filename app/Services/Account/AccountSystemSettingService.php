<?php

namespace App\Services\Account;

use App\Constant\MembershipSettingConstant;
use App\Repositories\Account\AccountSystemSettingRepository;

/**
 * Single generic per-account settings service (backed by the account_system_settings
 * EAV store). It is NOT tied to one feature: settings are declared as typed key
 * definitions and this service reads/casts/writes them generically.
 *
 * To add another settings group later, register its definitions alongside the
 * membership ones in {@see definitions()} — no new controller/service/repository needed.
 */
class AccountSystemSettingService
{
    public function __construct(
        private AccountSystemSettingRepository $repository
    ) {
    }

    /**
     * All known setting definitions (snake set_key => [camel, type, default]),
     * aggregated across every settings group.
     *
     * @return array<string, array{camel: string, type: string, default: mixed}>
     */
    private function definitions(): array
    {
        return MembershipSettingConstant::definitions();
    }

    /**
     * Get the account's settings as a typed, camelCase map
     * (stored values merged over defaults).
     *
     * @param int $accountId
     * @return array<string, mixed>
     */
    public function getForAccount(int $accountId): array
    {
        $stored = $this->repository->getAllForAccount($accountId);

        $out = [];
        foreach ($this->definitions() as $snakeKey => $def) {
            $raw = array_key_exists($snakeKey, $stored) ? $stored[$snakeKey] : $def['default'];
            $out[$def['camel']] = $this->cast($raw, $def['type']);
        }

        return $out;
    }

    /**
     * Get a single setting value (typed) by its camelCase key.
     *
     * @param int $accountId
     * @param string $camelKey
     * @return mixed
     */
    public function get(int $accountId, string $camelKey): mixed
    {
        return $this->getForAccount($accountId)[$camelKey] ?? null;
    }

    /**
     * Persist the given (camelCase) attributes, storing only known setting keys.
     *
     * @param int $accountId
     * @param array<string, mixed> $camelAttributes validated request data
     * @return array<string, mixed> the refreshed typed settings
     */
    public function update(int $accountId, array $camelAttributes): array
    {
        $camelToSnake = [];
        foreach ($this->definitions() as $snakeKey => $def) {
            $camelToSnake[$def['camel']] = $snakeKey;
        }

        $toStore = [];
        foreach ($camelAttributes as $camel => $value) {
            if (!isset($camelToSnake[$camel])) {
                continue;
            }
            $toStore[$camelToSnake[$camel]] = $this->serialize($value);
        }

        $this->repository->upsertForAccount($accountId, $toStore);

        return $this->getForAccount($accountId);
    }

    /**
     * Cast a stored string value back to its declared type.
     */
    private function cast(mixed $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $raw,
            'float' => (float) $raw,
            default => (string) $raw,
        };
    }

    /**
     * Serialize a typed value to the string form stored in set_value.
     */
    private function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
