<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProReturnController extends Controller
{
    public function __invoke(Request $request)
    {
        // Fulfillment is via webhooks; this page is just a friendly “thanks”
        return redirect()->route('pro.landing')->with('status', 'Thanks — we’re processing your upgrade!');
    }
}
