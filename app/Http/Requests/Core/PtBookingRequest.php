<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class PtBookingRequest extends GenericRequest
{

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => 'required|integer',
            'customerPtPackageId' => 'required|integer',
            'coachId' => 'required|integer',
            'bookingDate' => 'required|date',
            'bookingTime' => 'required',
            'duration' => 'required|integer',
            'bookingNotes' => 'nullable|string',
            'status' => 'sometimes|string',
        ]);
    }
}
