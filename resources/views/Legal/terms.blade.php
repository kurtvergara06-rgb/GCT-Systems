<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Terms of Use | FROMS</title>
  @vite(['resources/css/Main-styles/theme.css','resources/css/Login/legal.css'])
</head>
<body>
<main class="legal-page">
  <div class="legal-wrap">
    <nav class="legal-nav" aria-label="Legal pages">
      <a href="{{ route('login') }}">FROMS Sign In</a>
      <span aria-hidden="true">/</span>
      <span aria-current="page">Terms of Use</span>
    </nav>
    <article class="legal-card">
      <p class="legal-kicker">GCT Transport Services, Inc.</p>
      <h1>Terms of Use</h1>
      <p class="legal-intro">These terms describe expected use of the Fleet Resource Optimization and Management System (FROMS). The company's authorized representative should review and approve these terms before production publication.</p>

      <h2>Authorized use</h2>
      <p>FROMS is intended for authorized company operations. Account registration, email verification or Google sign-in does not independently grant access: account approval, assigned roles and system permissions still apply.</p>

      <h2>Account responsibility</h2>
      <p>Use your own approved account and keep credentials confidential. Do not share passwords, access another person's account or attempt to bypass verification, permissions or security controls. Report suspected account compromise through the company's approved support channel.</p>

      <h2>Operational data</h2>
      <p>Enter accurate and appropriate operational information, and only review or modify records within your assigned responsibilities. Follow required approvals for dispatch, maintenance, purchasing, inventory and other company workflows. Do not upload unrelated personal or confidential material.</p>

      <h2>Automated recommendations</h2>
      <p>Analytics, predictions and automation are decision-support tools. A recommendation is not a substitute for required human review, company policy or operational safety procedures.</p>

      <h2>Availability and security</h2>
      <p>Network connectivity, hosting and integrations may affect system availability. Authorized users should follow company procedures when service is disrupted. Unauthorized extraction, interference, reverse engineering for misuse, or attempts to defeat access controls are prohibited.</p>

      <h2>Privacy and changes</h2>
      <p>See the <a href="{{ route('legal.privacy') }}">Privacy Notice</a> for information about personal data processing. Company-authorized changes to these terms should be communicated to affected users as appropriate. This document does not replace company employment policies or applicable law.</p>

      <h2>Questions</h2>
      <p>For access, policy or system concerns, contact an authorized GCT Transport Services, Inc. administrator through the company's official communication channel.</p>
    </article>
    <footer class="legal-footer"><a href="{{ route('legal.privacy') }}">Privacy Notice</a><a href="{{ route('login') }}">Return to Sign In</a></footer>
  </div>
</main>
</body>
</html>
