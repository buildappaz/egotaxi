<?php

namespace App\Http\Controllers\API;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        //
    }
  
    public function build()
    {
        return "Sdfjakldsf jakls fkas lfkajsdf ";
    }
}