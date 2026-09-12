<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mints a service token for the operations console.
 *
 * The token itself is printed once and never stored — only its SHA-256 goes in
 * the environment, so a leaked .env or a config dump cannot be replayed against
 * this API. Losing the token means issuing a new one, which is the intended
 * trade: this token bypasses every account boundary in the product.
 */
class PlatformTokenGenerate extends Command
{
    protected $signature = 'platform:token {--length=48 : Token length in characters.}';

    protected $description = 'Generate a platform console service token and the PLATFORM_SERVICE_TOKEN_HASH to store';

    /**
     * @return int
     */
    public function handle(): int
    {
        $length = max(32, (int) $this->option('length'));
        $token = Str::random($length);

        $this->newLine();
        $this->line('  Give this token to the console (Projects → Create project → Service token).');
        $this->line('  It is shown once and cannot be recovered.');
        $this->newLine();
        $this->line('  <fg=yellow>'.$token.'</>');
        $this->newLine();
        $this->line('  Put this in this API\'s .env, then run `php artisan config:clear`:');
        $this->newLine();
        $this->line('  <fg=green>PLATFORM_SERVICE_TOKEN_HASH='.hash('sha256', $token).'</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
