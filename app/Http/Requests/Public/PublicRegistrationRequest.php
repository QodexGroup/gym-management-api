<?php

namespace App\Http\Requests\Public;

use App\Constant\PublicRegistrationConstant;
use App\Models\Account\Account;
use App\Rules\ValidEmail;
use App\Services\Account\AccountSystemSettingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a public member self-registration submission.
 *
 * Extends FormRequest directly rather than GenericRequest ON PURPOSE:
 * GenericRequest exposes getUserData()/getGenericData(), which resolve the
 * tenant from an authenticated user. There is no user here, and inheriting
 * those helpers invites a future change to silently reach for one.
 *
 * The rule set is also an allowlist. Staff-only fields are absent, so a
 * submitter cannot set them — most importantly membershipPlanId, which would
 * make CustomerService::create() assign a plan, set a balance from its price
 * AND generate a bill.
 */
class PublicRegistrationRequest extends FormRequest
{
    /**
     * Public endpoint: authorisation is handled by ResolvePublicAccountMiddleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules, with optional fields promoted to required per the
     * gym's own settings.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = fn (bool $isRequired) => $isRequired ? 'required' : 'nullable';
        $settings = $this->gymSettings();

        return [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'dateOfBirth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],

            'email' => [$required($settings['requireEmail']), 'max:255', new ValidEmail()],
            'address' => [$required($settings['requireAddress']), 'string', 'max:1000'],

            'emergencyContactName' => [$required($settings['requireEmergencyContact']), 'string', 'max:255'],
            'emergencyContactPhone' => [$required($settings['requireEmergencyContact']), 'string', 'max:20'],
            'emergencyContactRelationship' => ['nullable', 'string', 'max:100'],

            'bloodType' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medicalConditions' => ['nullable', 'string', 'max:1000'],
            'medicalNotes' => ['nullable', 'string', 'max:1000'],

            // Optional membership. Selecting a plan creates a membership AND an
            // unpaid bill, so the id is additionally checked against this gym's
            // own plans in PublicRegistrationService before it is used.
            'membershipPlanId' => ['nullable', 'integer', 'min:1'],
            // Defaults to today server-side when omitted. Backdating is refused:
            // a public visitor must not be able to start a membership in the past
            // and shorten (or pre-expire) what the gym then bills for.
            'membershipStartDate' => ['nullable', 'date', 'after_or_equal:today'],

            // Honeypot. Validated loosely so a bot gets no signal from a 422 —
            // the controller returns an ordinary success and writes nothing.
            PublicRegistrationConstant::HONEYPOT_FIELD => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Which optional fields this gym has made mandatory.
     *
     * @return array{requireEmail: bool, requireAddress: bool, requireEmergencyContact: bool}
     */
    private function gymSettings(): array
    {
        $account = $this->attributes->get('public_account');

        if (!$account instanceof Account) {
            return ['requireEmail' => false, 'requireAddress' => false, 'requireEmergencyContact' => false];
        }

        $settings = app(AccountSystemSettingService::class)->getForAccount($account->id);

        return [
            'requireEmail' => (bool) ($settings['kioskRegistrationRequireEmail'] ?? false),
            'requireAddress' => (bool) ($settings['kioskRegistrationRequireAddress'] ?? false),
            'requireEmergencyContact' => (bool) ($settings['kioskRegistrationRequireEmergencyContact'] ?? false),
        ];
    }

    /**
     * Friendlier messages — these are read by members, not developers.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dateOfBirth.before' => 'Date of birth cannot be in the future.',
            'dateOfBirth.after' => 'Please enter a valid date of birth.',
            'membershipStartDate.after_or_equal' => 'The start date cannot be in the past.',
        ];
    }
}
