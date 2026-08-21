<?php

namespace App\Warranty\Events;

use App\Warranty\Garantia;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GarantiaAcionada
{
    use Dispatchable, SerializesModels;

    public function __construct(public Garantia $garantia) {}
}
