<?php

namespace App\Constant;

/**
 * Vocabulary for the console-runnable command surface.
 *
 * The console never hardcodes a command key — it reads config/platform-commands.php
 * through /platform/commands at runtime. These constants describe the SHAPE of
 * an entry, which is the only thing both sides must agree on.
 */
class PlatformCommandConstant
{
    /** Runs across every account. The console offers these on the accounts list. */
    const SCOPE_GLOBAL = 'global';

    /** Runs for exactly one account. The console offers these on an account's page. */
    const SCOPE_ACCOUNT = 'account';

    const SCOPES = [self::SCOPE_GLOBAL, self::SCOPE_ACCOUNT];

    /** How a value reaches the artisan command. */
    const PASS_OPTION = 'option';
    const PASS_ARGUMENT = 'argument';

    const PASS_MODES = [self::PASS_OPTION, self::PASS_ARGUMENT];

    /** Input control the console renders for a declared parameter. */
    const INPUT_STRING = 'string';
    const INPUT_NUMBER = 'number';
    const INPUT_BOOLEAN = 'boolean';

    const INPUTS = [self::INPUT_STRING, self::INPUT_NUMBER, self::INPUT_BOOLEAN];

    /**
     * Console output is truncated before it is returned. A command that prints
     * a line per account would otherwise put an unbounded payload through the
     * console and into its audit log.
     */
    const MAX_OUTPUT_CHARS = 20000;

    /** Fallback when an entry declares no timeout. */
    const DEFAULT_TIMEOUT = 120;
}
