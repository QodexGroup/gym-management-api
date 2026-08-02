<?php

namespace App\Modules\Imports\Support;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Minimal import concern used only so Excel::toArray() has a target object.
 * The facade returns the parsed sheet array directly; this class stores nothing.
 */
class RawSheetImport implements ToArray
{
    /**
     * Required by the ToArray concern; intentionally a no-op.
     *
     * @param array $array
     * @return void
     */
    public function array(array $array): void
    {
        // no-op — Excel::toArray() returns the parsed rows to the caller.
    }
}
