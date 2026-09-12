<?php

namespace App\Services\Platform;

use App\Constant\PlatformCommandConstant as Cmd;
use App\Repositories\Platform\PlatformAccountRepository;
use App\Support\Platform\PlatformCommandRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Runs the allowlisted maintenance commands on behalf of the operations console.
 *
 * Business-rule refusals are thrown as RuntimeException, which bootstrap/app.php
 * renders as a 422 carrying the message — the operator reads WHY the run was
 * refused rather than a generic failure.
 */
class PlatformCommandService
{
    public function __construct(
        private PlatformCommandRegistry $registry,
        private PlatformAccountRepository $accountRepository,
    ) {
    }

    /**
     * Every command this product allows the console to run.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCommands(): array
    {
        return $this->registry->getCommands();
    }

    /**
     * One command definition, or null when it is not allowlisted here.
     *
     * @param string $key
     *
     * @return array<string, mixed>|null
     */
    public function findCommand(string $key): ?array
    {
        return $this->registry->findCommand($key);
    }

    /**
     * Run a command and capture what it printed.
     *
     * A non-zero exit code is NOT an exception: "the command ran and reported a
     * problem" is a result the operator needs to see in full, not an error page.
     * Only a thrown Throwable — the command itself blew up — is reported as a
     * failed run, and its message is captured into the output the same way.
     *
     * @param array<string, mixed> $definition
     * @param int|null $accountId
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function runCommand(array $definition, ?int $accountId, array $params): array
    {
        $this->assertRunnable($definition, $accountId, $params);

        $arguments = $this->buildArguments($definition, $accountId, $params);
        $buffer = new BufferedOutput();
        $startedAt = Carbon::now();
        $start = microtime(true);

        // Best effort only: Artisan::call runs in-process, so this caps the PHP
        // execution time rather than interrupting the command mid-statement.
        // Keep registry timeouts below the console's own HTTP timeout.
        @set_time_limit($definition['timeout']);

        try {
            $exitCode = Artisan::call($definition['command'], $arguments, $buffer);
            $output = $buffer->fetch();
        } catch (Throwable $e) {
            // The buffer holds whatever the command printed before it died —
            // usually the most useful part.
            $exitCode = 1;
            $output = $buffer->fetch()."\n".get_class($e).': '.$e->getMessage();
        }

        $finishedAt = Carbon::now();
        $truncated = mb_strlen($output) > Cmd::MAX_OUTPUT_CHARS;

        return [
            'key' => $definition['key'],
            'command' => $definition['command'],
            'accountId' => $accountId,
            'exitCode' => $exitCode,
            'ok' => $exitCode === 0,
            'output' => $truncated ? mb_substr($output, 0, Cmd::MAX_OUTPUT_CHARS)."\n… output truncated" : $output,
            'truncated' => $truncated,
            'durationMs' => (int) round((microtime(true) - $start) * 1000),
            'startedAt' => $startedAt->toIso8601String(),
            'finishedAt' => $finishedAt->toIso8601String(),
        ];
    }

    /**
     * Refuse a run the definition does not support, before anything executes.
     *
     * @param array<string, mixed> $definition
     * @param int|null $accountId
     * @param array<string, mixed> $params
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function assertRunnable(array $definition, ?int $accountId, array $params): void
    {
        if ($definition['scope'] === Cmd::SCOPE_ACCOUNT) {
            if ($accountId === null) {
                throw new RuntimeException("\"{$definition['label']}\" runs for one account and no account was given.");
            }

            if (!$this->accountRepository->accountExists($accountId)) {
                throw new RuntimeException("Account #{$accountId} does not exist on this product.");
            }
        }

        // A global command handed an account id would run across EVERY account
        // while the audit log says it was run "for" one. Refuse rather than
        // record something untrue.
        if ($definition['scope'] === Cmd::SCOPE_GLOBAL && $accountId !== null) {
            throw new RuntimeException("\"{$definition['label']}\" runs across every account and cannot be scoped to one.");
        }

        foreach ($definition['params'] as $param) {
            $given = $params[$param['name']] ?? null;

            if ($param['required'] && ($given === null || $given === '')) {
                throw new RuntimeException("\"{$param['label']}\" is required to run \"{$definition['label']}\".");
            }
        }
    }

    /**
     * Map the account id and declared parameters onto artisan's argument array.
     *
     * Only DECLARED parameters are passed. Anything else the caller sent is
     * dropped — the console must never be able to inject an option the registry
     * did not describe.
     *
     * @param array<string, mixed> $definition
     * @param int|null $accountId
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildArguments(array $definition, ?int $accountId, array $params): array
    {
        $arguments = [];

        if ($accountId !== null && $definition['accountParam'] !== null) {
            $arguments[$this->argumentName($definition['accountParam'])] = $accountId;
        }

        foreach ($definition['params'] as $param) {
            if (!array_key_exists($param['name'], $params)) {
                continue;
            }

            $value = $params[$param['name']];

            if ($value === null || $value === '') {
                continue;
            }

            if ($param['input'] === Cmd::INPUT_BOOLEAN) {
                // A false boolean option is expressed by omitting the flag.
                if (filter_var($value, FILTER_VALIDATE_BOOL)) {
                    $arguments[$this->argumentName($param)] = true;
                }

                continue;
            }

            $arguments[$this->argumentName($param)] = $param['input'] === Cmd::INPUT_NUMBER
                ? (int) $value
                : (string) $value;
        }

        return $arguments;
    }

    /**
     * Artisan names options with a leading `--` and arguments bare.
     *
     * @param array<string, mixed> $param Carries `name` plus `type` or `passAs`.
     *
     * @return string
     */
    private function argumentName(array $param): string
    {
        $mode = $param['type'] ?? $param['passAs'] ?? Cmd::PASS_OPTION;

        return $mode === Cmd::PASS_ARGUMENT ? $param['name'] : '--'.$param['name'];
    }
}
