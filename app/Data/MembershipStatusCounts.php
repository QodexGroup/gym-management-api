<?php

namespace App\Data;

class MembershipStatusCounts
{
    /** Every client matching the current search / coach filters, with or without a membership. */
    public int $total;
    public int $active;
    public int $expiringSoon;
    public int $expired;
}
