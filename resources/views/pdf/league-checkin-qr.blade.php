<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>League Check-in QR</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 1in; }
    .wrap { text-align: center; }
    h1 { font-size: 20pt; margin: 0 0 4pt; }
    .sub { color: #555; font-size: 11pt; margin-bottom: 24pt; }
    .qr { display: inline-block; width: 5.5in; }  /* scales the SVG cleanly */
    .url { margin-top: 18pt; font-size: 10pt; color: #333; word-break: break-all; }
    .note { margin-top: 6pt; font-size: 9pt; color: #777; }
    img.qrsvg { width: 100%; height: auto; display: block; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>{{ $league->title }} — Check-in</h1>
    <div class="sub">Scan this QR to open public check-in</div>

    <div class="qr">
      <!-- Embed SVG as base64 image so DomPDF renders it -->
      <img class="qrsvg" src="data:image/svg+xml;base64,{{ $qrSvgB64 }}" alt="QR Code">
    </div>

    <div class="url">{{ $url }}</div>
    <div class="note">Tip: Post this at the venue entrance or the registration desk.</div>
  </div>
</body>
</html>
