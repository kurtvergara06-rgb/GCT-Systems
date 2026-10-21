<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GCT-Systems Email Verification</title>
</head>
<body style="margin:0;padding:28px 12px;background:#f2f5f9;font-family:Arial,Helvetica,sans-serif;color:#17304f;">
  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:540px;margin:auto;background:#ffffff;border:1px solid #e2e9f2;border-radius:14px;">
    <tr><td style="padding:28px 30px 18px;text-align:center;border-bottom:3px solid #ffca05;">
      <img src="{{ $message->embed(public_path('img/gct_logo.png')) }}" width="72" height="72" alt="GCT Transport Services logo" style="display:block;width:72px;height:72px;object-fit:contain;margin:0 auto 12px;">
      <strong style="font-size:17px;color:#102f57;">GCT Transport Services, Inc.</strong>
    </td></tr>
    <tr><td style="padding:30px;text-align:center;">
      <h1 style="font-size:23px;line-height:1.3;margin:0 0 13px;color:#102f57;">Verify Your Email</h1>
      <p style="font-size:14px;color:#52657c;line-height:1.7;margin:0 0 23px;">Thank you for registering with GCT-Systems. Enter this verification code to confirm your email address.</p>
      <div style="background:#f0f5fb;border-radius:10px;padding:22px 14px;margin-bottom:20px;">
        <div style="font-size:11px;letter-spacing:2px;font-weight:bold;color:#58708d;margin-bottom:10px;">YOUR VERIFICATION CODE</div>
        <div style="font-size:34px;letter-spacing:8px;font-weight:bold;color:#0d3566;">{{ $code }}</div>
        <div style="font-size:12px;color:#647791;margin-top:10px;">Expires in 5 minutes</div>
      </div>
      <p style="font-size:13px;color:#66788e;line-height:1.65;margin:0;">Never share this code with anyone. If you did not request it, you can safely ignore this email.</p>
    </td></tr>
    <tr><td style="border-top:1px solid #e7ecf3;padding:19px;text-align:center;font-size:11px;color:#7a8ba2;">GCT Transport Services, Inc. · Account Security</td></tr>
  </table>
</body>
</html>
