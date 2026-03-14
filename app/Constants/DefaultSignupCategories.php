<?php

namespace App\Constants;

/**
 * Default category names seeded when a new account owner signs up.
 */
final class DefaultSignupCategories
{
    /** @var list<string> */
    public const DEFAULT_EXPENSE_CATEGORIES = [
        'Rent',
        'Salary',
        'Equipment',
        'Utilities',
        'Maintenance',
        'Supplies',
    ];

    /** @var list<string> */
    public const DEFAULT_PT_CATEGORIES = [
        'Strength & Conditioning',
        'Cardio & Endurance',
        'Weight Loss Program',
        'HIIT Training',
        'Flexibility & Mobility',
        'CrossFit',
        'Yoga',
        'Pilates',
    ];
}
