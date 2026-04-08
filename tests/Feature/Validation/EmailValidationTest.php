<?php

namespace Tests\Feature\Validation;

use App\Rules\ValidEmail;
use Faker\Factory as FakerFactory;
use PHPUnit\Framework\TestCase;

class EmailValidationTest extends TestCase
{
    private function passesValidEmailRule(mixed $value): bool
    {
        $rule = new ValidEmail();
        $failed = false;

        $rule->validate('email', $value, function () use (&$failed): void {
            $failed = true;
        });

        return !$failed;
    }

    public function test_valid_email_rule_rejects_malformed_emails_using_faker(): void
    {
        $faker = FakerFactory::create();
        $faker->seed(1337);

        $valid1 = $faker->unique()->safeEmail();
        $valid2 = $faker->unique()->email();

        $this->assertTrue($this->passesValidEmailRule($valid1));
        $this->assertTrue($this->passesValidEmailRule($valid2));
        $this->assertTrue($this->passesValidEmailRule(" {$valid1} "));

        $this->assertFalse($this->passesValidEmailRule(''));
        $this->assertFalse($this->passesValidEmailRule($faker->word()));
        $this->assertFalse($this->passesValidEmailRule($faker->word() . '@'));
        $this->assertFalse($this->passesValidEmailRule($faker->word() . '@' . $faker->word()));
        $this->assertFalse($this->passesValidEmailRule($faker->word() . '@' . $faker->word() . '.'));
        $this->assertFalse($this->passesValidEmailRule('a b@c.com'));
        $this->assertFalse($this->passesValidEmailRule('a@b@c.com'));
    }

    public function test_request_rules_use_valid_email_rule_using_dummy_data(): void
    {
        // Pure dummy verification: assert request classes reference the shared rule in code.
        // This avoids booting Laravel (Auth facade, DB drivers, routing).
        $root = dirname(__DIR__, 3);

        $signUpRequest = file_get_contents($root . DIRECTORY_SEPARATOR . 'app/Http/Requests/Auth/SignUpRequest.php');
        $this->assertIsString($signUpRequest);
        $this->assertStringContainsString('use App\\Rules\\ValidEmail;', $signUpRequest);
        $this->assertStringContainsString("'email' => ['required', new ValidEmail()", $signUpRequest);
        $this->assertStringContainsString("'billingEmail' => ['required', 'max:255', new ValidEmail()", $signUpRequest);

        $userRequest = file_get_contents($root . DIRECTORY_SEPARATOR . 'app/Http/Requests/Account/UserRequest.php');
        $this->assertIsString($userRequest);
        $this->assertStringContainsString('use App\\Rules\\ValidEmail;', $userRequest);
        $this->assertStringContainsString('new ValidEmail()', $userRequest);

        $customerRequest = file_get_contents($root . DIRECTORY_SEPARATOR . 'app/Http/Requests/Core/CustomerRequest.php');
        $this->assertIsString($customerRequest);
        $this->assertStringContainsString('use App\\Rules\\ValidEmail;', $customerRequest);
        $this->assertStringContainsString("'email' => ['nullable', 'max:255', new ValidEmail()", $customerRequest);
    }
}

