<?php

namespace App\Http\Controllers;

use App\Models\League;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LeagueQrController extends Controller
{
    public function downloadCheckinQr(League $league)
    {
        Gate::authorize('view', $league);

        $url = route('public.cls.participants', [
            'kind' => 'league',
            'uuid' => $league->public_uuid,
        ]);

        // Generate SVG QR (vector, no Imagick), then base64 encode for DomPDF
        $svg = QrCode::format('svg')
            ->size(900)        // large internal canvas; CSS will scale in PDF
            ->margin(0)
            ->errorCorrection('M')
            ->generate($url);

        $svgBase64 = base64_encode($svg);

        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true, // safer SVG handling
            'isRemoteEnabled' => true, // allow data: URLs
        ])
            ->loadView('pdf.league-checkin-qr', [
                'league' => $league,
                'url' => $url,
                'qrSvgB64' => $svgBase64,
            ])
            ->setPaper('letter', 'portrait');

        return $pdf->download('league-'.$league->id.'-checkin-qr.pdf');
    }
}
