<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CheckoutReturnController extends Controller
{
    public function __invoke(Request $request)
    {
        // Optionally read session_id and show status; we rely on the webhook to do the real work
        return redirect()->route('dashboard')->with('status', 'Thanks! If your payment was successful, your registration will appear shortly.');
    }
}
