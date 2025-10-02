@extends('landing.page')

@section('title', 'Terms & Conditions')

@section('page')
  <h2>Terms & Conditions</h2>
  <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
    Effective date: {{ now()->format('F j, Y') }}
  </p>

  <p>
    These Terms & Conditions (“Terms”) govern your access to and use of ArcherDB 
    (“we”, “our”, or “us”) services, including our website, applications, and 
    any related tools (the “Services”). By using our Services, you agree to 
    these Terms.
  </p>

  <h3>1. Eligibility</h3>
  <p>
    You must be at least 13 years old (or the age of majority in your jurisdiction) 
    to create an account or use our Services. If you are under this age, you may only 
    use the Services with the involvement of a parent or guardian.
  </p>

  <h3>2. Accounts</h3>
  <ul>
    <li>You are responsible for maintaining the confidentiality of your login credentials.</li>
    <li>You agree to provide accurate, up-to-date information when registering.</li>
    <li>You are responsible for all activities that occur under your account.</li>
  </ul>

  <h3>3. Acceptable Use</h3>
  <p>
    You agree not to use the Services in any way that:
  </p>
  <ul>
    <li>Violates applicable laws or regulations.</li>
    <li>Infringes on the rights of others.</li>
    <li>Harasses, abuses, or threatens other users.</li>
    <li>Attempts to disrupt or compromise the security of the Services.</li>
  </ul>

  <h3>4. Content</h3>
  <p>
    Any content you upload (e.g., training sessions, league data, comments) remains 
    your property. By submitting content, you grant us a non-exclusive, worldwide, 
    royalty-free license to use, display, and distribute that content as necessary 
    to provide the Services.
  </p>

  <h3>5. Payment & Subscriptions</h3>
  <p>
    Certain features may require a paid subscription. All fees are due in advance 
    and are non-refundable, except where required by law. We reserve the right to 
    change pricing with reasonable notice.
  </p>

  <h3>6. Termination</h3>
  <p>
    We may suspend or terminate your account if you violate these Terms or use 
    the Services in a manner that could cause harm to us or others. You may 
    terminate your account at any time by contacting support.
  </p>

  <h3>7. Limitation of Liability</h3>
  <p>
    To the maximum extent permitted by law, ArcherDB shall not be liable for 
    any indirect, incidental, or consequential damages arising out of your 
    use of the Services.
  </p>

  <h3>8. Changes to These Terms</h3>
  <p>
    We may update these Terms from time to time. Any changes will be posted 
    on this page with a revised effective date. Continued use of the Services 
    after changes are made constitutes acceptance of the new Terms.
  </p>

  <h3>9. Governing Law</h3>
  <p>
    These Terms are governed by and construed in accordance with the laws 
    of your jurisdiction, without regard to conflict of law principles.
  </p>

  <h3>10. Contact Us</h3>
  <p>
    If you have questions about these Terms, please contact us at:
  </p>
  <p>
    <strong>Email:</strong> support@archerdb.cloud<br>
    <strong>Address:</strong> ArcherDB, [Your Company Address]
  </p>
@endsection
