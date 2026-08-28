<?php

namespace App\Data;

/**
 * Everything the public registration page is allowed to know about a gym.
 *
 * Deliberately minimal: no member counts, no staff names, no contact details,
 * nothing about the account's plan, billing or status. This object is served to
 * anyone holding the link, so any field added here becomes public.
 */
class PublicGymRegistrationInfo
{
    public string $gymName;
    public string $welcomeText;
    public string $successText;
    public bool $requireEmail;
    public bool $requireAddress;
    public bool $requireEmergencyContact;

    /**
     * Plans the gym offers, as [id, planName, price, planPeriod, planInterval].
     *
     * NOTE: this makes plan names and prices publicly readable to anyone holding
     * the registration link. That is normally fine (gyms post their rates), but
     * it is a deliberate widening of what this endpoint exposes.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $membershipPlans;
}
