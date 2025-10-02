@extends('landing.layouts.page')

@section('title', 'Privacy Policy')

@section('page')
    <h2>Privacy Policy</h2>
    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
        Effective date: {{ now()->format('F j, Y') }}
    </p>

    <p class="mt-4">
        ArcherDB (“we”, “our”, or “us”) is committed to protecting your privacy.
        This Privacy Policy explains how we collect, use, and safeguard your information
        when you use our website, services, and applications (collectively, the “Services”).
    </p>

    <h3>Information We Collect</h3>
    <ul>
        <li><strong>Account Information:</strong> When you register, we collect your name, email address, and password.</li>
        <li><strong>Profile Data:</strong> Optional details like your club, country, or archery profile information.</li>
        <li><strong>Usage Data:</strong> Information on how you interact with our Services (e.g., training sessions,
            leagues).</li>
        <li><strong>Device & Technical Data:</strong> IP address, browser type, and cookies to help improve the site.</li>
    </ul>

    <h3>How We Use Your Information</h3>
    <ul>
        <li>To provide and maintain our Services.</li>
        <li>To personalize your experience and display relevant content.</li>
        <li>To communicate updates, support, and promotional offers (if opted in).</li>
        <li>To analyze usage and improve features.</li>
        <li>To comply with legal obligations.</li>
    </ul>

    <h3>Sharing of Information</h3>
    <p>
        We do not sell or rent your personal information. We may share data with:
    </p>
    <ul>
        <li>Service providers who help operate the Services (e.g., hosting, analytics).</li>
        <li>Clubs, leagues, or competitions you voluntarily join within the platform.</li>
        <li>Authorities, if required by law or to protect rights and safety.</li>
    </ul>

    <h3>Cookies & Tracking</h3>
    <p>
        We use cookies and similar technologies to keep you logged in, remember your preferences,
        and analyze how the Services are used. You can manage cookies in your browser settings.
    </p>

    <h3>Data Retention</h3>
    <p>
        We keep your information only as long as necessary to provide the Services and fulfill the
        purposes described in this policy, unless a longer retention is required by law.
    </p>

    <h3>Your Rights</h3>
    <ul>
        <li>Access, update, or delete your personal data.</li>
        <li>Opt out of marketing communications at any time.</li>
        <li>Request a copy of your data.</li>
    </ul>

    <h3>Security</h3>
    <p>
        We implement reasonable technical and organizational measures to protect your information.
        However, no system is completely secure, and we cannot guarantee absolute security.
    </p>

    <h3>Children’s Privacy</h3>
    <p>
        Our Services are not directed to individuals under the age of 13 (or the minimum age in your jurisdiction).
        We do not knowingly collect personal data from children.
    </p>

    <h3>Changes to This Policy</h3>
    <p>
        We may update this Privacy Policy from time to time. Any changes will be posted on this page with a revised date.
    </p>

    <h3>Contact Us</h3>
    <p>
        If you have questions about this Privacy Policy or how your information is handled,
        please contact us at:
    </p>
    <p>
        <strong>Email:</strong> support@archerdb.cloud<br>
        <strong>Address:</strong> ArcherDB, [Your Company Address]
    </p>
@endsection
