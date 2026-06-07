<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

abstract class BaseEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function middleware(): array
    {
        return [new RateLimited('emails')];
    }

    /**
     * Subclasses implement the actual email sending logic here.
     * Early returns (missing model, no email) should happen inside this method.
     */
    abstract protected function execute(): void;

    public function handle(): void
    {
        try {
            $this->execute();
        } catch (\Throwable $th) {
            Log::error(class_basename(static::class) . ' failed', [
                'error' => $th->getMessage(),
                'job' => static::class,
            ]);
            throw $th;
        }
    }
}
