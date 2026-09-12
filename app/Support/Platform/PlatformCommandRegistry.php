<?php

namespace App\Support\Platform;

use App\Constant\PlatformCommandConstant as Cmd;
use Illuminate\Support\Facades\Log;

/**
 * Reads config/platform-commands.php and hands back normalised definitions.
 *
 * Every lookup goes through here, so there is exactly one place that decides
 * whether a command is runnable. A key absent from the config is absent from
 * this registry, and therefore unrunnable — there is no fallback path that
 * resolves a command by name.
 *
 * A malformed entry is DROPPED and logged rather than throwing: one bad entry
 * in a config file must not take the whole platform surface down, and a command
 * that silently fails to appear is the safe direction to fail in.
 */
class PlatformCommandRegistry
{
    /**
     * Every runnable command, normalised.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCommands(): array
    {
        $entries = (array) config('platform-commands.commands', []);
        $normalised = [];
        $seen = [];

        foreach ($entries as $entry) {
            $definition = $this->normalise((array) $entry);

            if ($definition === null) {
                continue;
            }

            // A duplicate key would make `find()` order-dependent, and the audit
            // log ambiguous about which one ran.
            if (isset($seen[$definition['key']])) {
                Log::warning('Duplicate platform command key ignored', ['key' => $definition['key']]);
                continue;
            }

            $seen[$definition['key']] = true;
            $normalised[] = $definition;
        }

        return $normalised;
    }

    /**
     * One command by its console-facing key, or null when it is not allowlisted.
     *
     * @param string $key
     *
     * @return array<string, mixed>|null
     */
    public function findCommand(string $key): ?array
    {
        foreach ($this->getCommands() as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Validate and fill in one config entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>|null Null when the entry is unusable.
     */
    private function normalise(array $entry): ?array
    {
        $key = $entry['key'] ?? null;
        $command = $entry['command'] ?? null;
        $scope = $entry['scope'] ?? null;

        if (!is_string($key) || $key === '' || !is_string($command) || $command === '') {
            Log::warning('Platform command entry missing key or command', ['entry' => $entry]);

            return null;
        }

        if (!in_array($scope, Cmd::SCOPES, true)) {
            Log::warning('Platform command entry has an unknown scope', ['key' => $key, 'scope' => $scope]);

            return null;
        }

        $accountParam = $this->normaliseAccountParam($entry, $key, $scope);

        if ($scope === Cmd::SCOPE_ACCOUNT && $accountParam === null) {
            return null;
        }

        return [
            'key' => $key,
            'command' => $command,
            'label' => (string) ($entry['label'] ?? $key),
            'description' => (string) ($entry['description'] ?? ''),
            'scope' => $scope,
            'accountParam' => $accountParam,
            'destructive' => (bool) ($entry['destructive'] ?? false),
            'timeout' => max(1, (int) ($entry['timeout'] ?? Cmd::DEFAULT_TIMEOUT)),
            'params' => $this->normaliseParams((array) ($entry['params'] ?? []), $key),
        ];
    }

    /**
     * How the account id reaches an account-scoped command.
     *
     * @param array<string, mixed> $entry
     * @param string $key
     * @param string $scope
     *
     * @return array{type: string, name: string}|null
     */
    private function normaliseAccountParam(array $entry, string $key, string $scope): ?array
    {
        if ($scope !== Cmd::SCOPE_ACCOUNT) {
            return null;
        }

        $accountParam = (array) ($entry['accountParam'] ?? []);
        $type = $accountParam['type'] ?? null;
        $name = $accountParam['name'] ?? null;

        if (!in_array($type, Cmd::PASS_MODES, true) || !is_string($name) || $name === '') {
            Log::warning('Account-scoped platform command has no usable accountParam', ['key' => $key]);

            return null;
        }

        return ['type' => $type, 'name' => $name];
    }

    /**
     * Declared extra inputs, dropping anything the console could not render.
     *
     * @param array<int, mixed> $params
     * @param string $key
     *
     * @return array<int, array<string, mixed>>
     */
    private function normaliseParams(array $params, string $key): array
    {
        $normalised = [];

        foreach ($params as $param) {
            $param = (array) $param;
            $name = $param['name'] ?? null;

            if (!is_string($name) || $name === '') {
                Log::warning('Platform command parameter has no name', ['key' => $key]);
                continue;
            }

            $input = $param['input'] ?? Cmd::INPUT_STRING;
            $passAs = $param['passAs'] ?? Cmd::PASS_OPTION;

            $normalised[] = [
                'name' => $name,
                'label' => (string) ($param['label'] ?? $name),
                'input' => in_array($input, Cmd::INPUTS, true) ? $input : Cmd::INPUT_STRING,
                'passAs' => in_array($passAs, Cmd::PASS_MODES, true) ? $passAs : Cmd::PASS_OPTION,
                'required' => (bool) ($param['required'] ?? false),
                'hint' => (string) ($param['hint'] ?? ''),
            ];
        }

        return $normalised;
    }
}
