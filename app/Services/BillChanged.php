<?php

namespace App\Services;

use App\Models\Appointment;
use RuntimeException;

/** The amount due changed while a payment screen was open (e.g. an extra was added). */
class BillChanged extends RuntimeException
{
    public function __construct(public readonly Appointment $appointment, string $message)
    {
        parent::__construct($message);
    }
}
