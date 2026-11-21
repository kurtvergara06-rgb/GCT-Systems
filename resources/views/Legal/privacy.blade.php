<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Privacy Notice | FROMS</title>
  @vite(['resources/css/Main-styles/theme.css','resources/css/Login/legal.css'])
</head>
<body>
<main class="legal-page">
  <div class="legal-wrap">
    <nav class="legal-nav" aria-label="Legal pages">
      <a href="{{ route('login') }}">FROMS Sign In</a>
      <span aria-hidden="true">/</span>
      <span aria-current="page">Privacy Notice</span>
    </nav>
    <article class="legal-card">
      <p class="legal-kicker">GCT Transport Services, Inc.</p>
      <h1>Privacy Notice</h1>
      <p class="legal-intro">This notice explains how the Fleet Resource Optimization and Management System (FROMS) processes information used for employee access and fleet operations. This notice should be reviewed and completed by the company's authorized privacy representative before production publication.</p>

      <h2>Information processed</h2>
      <p>Depending on your role and activities, FROMS may process account details (such as name, work email, roles and verification status), access and security records, and operational information entered in the system, including trip, driver, vehicle, maintenance, warehouse and purchasing records. Information is collected through account registration, authorized users and integrations used for operations.</p>

      <h2>Purposes and lawful grounds</h2>
      <p>Information is used to manage access, verify accounts, support authorized fleet operations, administer company processes, protect the system and maintain appropriate audit records. Processing must be supported by an applicable lawful basis under Philippine data protection law. Registration acknowledgment does not substitute for a required consent or other lawful basis.</p>

      <h2>Who may access information</h2>
      <p>Access is managed through employee roles and permissions. Authorized administrators and staff may access information required for their duties. Necessary service providers and integrations, including email delivery, Google sign-in and hosting providers, may process limited information to support the relevant services, subject to appropriate arrangements and safeguards.</p>

      <h2>Storage, security and retention</h2>
      <p>FROMS uses authentication, role-based access and application security controls. No online service can guarantee absolute security. Records should be retained only for established business, contractual, security or legal purposes, then disposed of securely under company retention rules. Specific retention periods and vendor arrangements must be confirmed with the company.</p>

      <h2>Analytics and automated assistance</h2>
      <p>FROMS may use operational data for reports, predictions and rule-based or machine-learning recommendations. Recommendations should be reviewed by authorized staff where required by the operational workflow. This notice does not claim that user-submitted data is used to train external AI models.</p>

      <h2>Your privacy rights and requests</h2>
      <p>Subject to applicable law, data subjects may request access, correction, or other appropriate actions concerning their personal data. To submit a request, contact the company's designated privacy officer or an authorized system administrator through the company's approved communication channel. The company must verify requests and assess applicable legal obligations before acting on them.</p>

      <h2>Cookies and device storage</h2>
      <p>The application uses browser/session mechanisms needed for sign-in, security and system operation. Any additional analytics or optional tracking technologies should be disclosed and assessed separately before being enabled. FROMS Desktop connects to the online system; sensitive operational records are not intended for offline storage.</p>

      <h2>Updates and company contact</h2>
      <p>This is an initial application notice and must be aligned with GCT Transport Services, Inc.'s approved privacy practices, actual service providers, retention schedule, contact details and publication date. For questions, contact the authorized company representative or system administrator.</p>
    </article>
    <footer class="legal-footer"><a href="{{ route('legal.terms') }}">Terms of Use</a><a href="{{ route('login') }}">Return to Sign In</a></footer>
  </div>
</main>
</body>
</html>
