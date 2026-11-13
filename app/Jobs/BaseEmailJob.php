<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\HttpTransportException;

abstract class BaseEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;

    /**
     * Escalating waits between attempts, in seconds.
     *
     * Without this the attempts fire back to back within the same second,
     * which is useless against the failures that actually happen here: a
     * provider rate limit, a brief API outage, or a sending IP that a person
     * has to authorise. Spreading five attempts over roughly 50 minutes means
     * those clear on their own.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900, 1800];

    /**
     * Provider HTTP statuses that no retry can fix, because the message itself
     * is what was rejected: malformed, too large, or failing validation.
     *
     * 401 and 403 are deliberately absent. Brevo answers 401 both for a key it
     * does not recognise and for a sending IP missing from its authorised list,
     * and the response cannot tell the two apart. The second needs a person to
     * add the IP, which usually takes minutes and so falls well inside the
     * ladder above, where retrying delivers the email that failing fast would
     * have thrown away. 404 is absent for a related reason: against a live API
     * host it more often comes from something sitting in front of the provider
     * than from the provider itself.
     */
    private const PERMANENT_STATUSES = [400, 413, 422];

    /**
     * The address this job is sending to, set by the subclass as soon as it is
     * known so the failure handlers can name the person who lost an email.
     */
    protected ?string $recipient = null;

    /**
     * Queue middleware applied to every email job.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('emails')];
    }

    /**
     * Subclasses implement the actual email sending logic here.
     * Early returns (missing model, no email) should happen inside this method.
     *
     * @return void
     */
    abstract protected function execute(): void;

    /**
     * Run the send, classifying any failure as permanent or worth retrying.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->execute();
        } catch (\Throwable $th) {
            $status = $this->transportStatusCode($th);
            $isPermanent = $this->isPermanentFailure($status);

            Log::error(class_basename(static::class) . ' failed', [
                'job' => static::class,
                'error' => $th->getMessage(),
                'mailer' => config('mail.default'),
                'transport_status' => $status,
                'permanent' => $isPermanent,
                'attempt' => $this->attempts(),
                'recipient' => $this->recipientForLog(),
            ]);

            if ($isPermanent) {
                // Another attempt would be sent to the same rejection, so stop
                // here. fail() runs failed() straight away, which records the
                // lost email now rather than 50 minutes from now.
                $this->fail($th);

                return;
            }

            throw $th;
        }
    }

    /**
     * Runs once the job is given up on, whether the attempts ran out, it timed
     * out, or handle() failed it outright. This is the only place that knows an
     * email is permanently lost, so it has to leave a trace naming the person.
     *
     * @param \Throwable|null $th the cause, or null when the queue gave no exception
     * @return void
     */
    public function failed(?\Throwable $th): void
    {
        Log::error(class_basename(static::class) . ' gave up; email was not delivered', [
            'job' => static::class,
            'mailer' => config('mail.default'),
            'recipient' => $this->recipientForLog(),
            'transport_status' => $th !== null ? $this->transportStatusCode($th) : null,
            'error' => $th?->getMessage(),
        ]);

        $this->onGaveUp($th);
    }

    /**
     * Who the email was for, so a lost one can be traced back to a person.
     * Null means the address could not be determined at all.
     *
     * Laravel runs failed() on a job object rebuilt from the queue payload, not
     * the instance that did the sending, so the address captured during
     * execute() is gone by then and has to be looked up again. The lookup is
     * guarded: on the failure path the database may be exactly what broke, and
     * a throw from inside failed() would replace the real report with a
     * secondary error.
     *
     * @return string|null the address, or null when it could not be determined
     */
    protected function recipientForLog(): ?string
    {
        if ($this->recipient !== null) {
            return $this->recipient;
        }

        try {
            return $this->recipient = $this->resolveRecipient();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Look the address up from the job's own ids, for the case where it was
     * never captured during the send. Subclasses override this.
     *
     * @return string|null the address, or null when this job cannot resolve one
     */
    protected function resolveRecipient(): ?string
    {
        return null;
    }

    /**
     * Hook for subclasses that need to release state they reserved before
     * dispatch, such as a dedupe row that would otherwise suppress a resend.
     * Runs on every give-up, not only on a permanent provider failure.
     *
     * @param \Throwable|null $th the cause, or null when the queue gave no exception
     * @return void
     */
    protected function onGaveUp(?\Throwable $th): void
    {
        //
    }

    /**
     * Whether retrying could ever help.
     *
     * A failure carrying no provider status - an SMTP error, a DNS failure, a
     * bug of our own - is treated as transient, so the default is to retry
     * rather than to drop an email.
     *
     * @param int|null $status the provider's HTTP status, or null when there is none
     * @return bool true when another attempt could not possibly succeed
     */
    protected function isPermanentFailure(?int $status): bool
    {
        if ($status === null) {
            return false;
        }

        // The provider accepted the message and something after that went
        // wrong, such as a response body we could not read. Sending again
        // would deliver a second copy.
        if ($status >= 200 && $status < 300) {
            return true;
        }

        return \in_array($status, self::PERMANENT_STATUSES, true);
    }

    /**
     * The provider's HTTP status, or null when the failure carries none.
     *
     * failed() is handed the wrapper Laravel throws when the attempts run out
     * or the job times out, so the chain is walked for the real cause rather
     * than reading only the outermost exception.
     *
     * @param \Throwable $th the failure, possibly wrapping the transport exception
     * @return int|null the provider's HTTP status, or null when the failure carries none
     */
    protected function transportStatusCode(\Throwable $th): ?int
    {
        for ($exception = $th; $exception !== null; $exception = $exception->getPrevious()) {
            if ($exception instanceof HttpTransportException) {
                return $this->statusFromTransportException($exception);
            }
        }

        return null;
    }

    /**
     * Symfony's API transports throw with an exception code of 0 and put the
     * real status on the response, falling back to the message text for the
     * case where the response never resolved.
     *
     * @param HttpTransportException $th the transport failure to read the status from
     * @return int|null the HTTP status, or null when neither source yields one
     */
    private function statusFromTransportException(HttpTransportException $th): ?int
    {
        try {
            return $th->getResponse()->getStatusCode();
        } catch (\Throwable) {
            // The transport could not reach the provider at all, so there is
            // no status to read off the response.
        }

        if (preg_match('/\(code (\d{3})\)/', $th->getMessage(), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
