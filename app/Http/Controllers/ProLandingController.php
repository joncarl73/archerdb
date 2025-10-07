<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProLandingController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $priceCents = (int) config('payments.pro_price_cents_year', 599);
        $priceDisplay = number_format($priceCents / 100, 2, '.', '');

        return view('pro.landing', [
            'isPro' => $user?->isPro() ?? false,
            'priceDisplay' => $priceDisplay,
        ]);
    }
}
