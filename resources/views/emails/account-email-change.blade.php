<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify Your New GCT-Systems Email</title></head>
<body style="margin:0;padding:28px 12px;background:#f2f5f9;font-family:Arial,Helvetica,sans-serif;color:#17304f">
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:540px;margin:auto;background:#fff;border:1px solid #e2e9f2;border-radius:14px">
<tr><td style="padding:28px 30px 18px;text-align:center;border-bottom:3px solid #ffca05">
@if(is_string(config('mail.gct_logo_url')) && str_starts_with(config('mail.gct_logo_url'), 'https://'))
<img src="{{ config('mail.gct_logo_url') }}" width="72" height="72" alt="GCT Transport Services logo" style="display:block;width:72px;height:72px;object-fit:contain;margin:0 auto 12px">
@endif
<strong style="font-size:17px;color:#102f57">GCT Transport Services, Inc.</strong>
</td></tr>
<tr><td style="padding:30px;text-align:center">
<h1 style="font-size:23px;line-height:1.3;margin:0 0 13px;color:#102f57">Verify Your New Email</h1>
<p style="font-size:14px;color:#52657c;line-height:1.7;margin:0 0 25px">An administrator requested an email address change for your GCT-Systems account. Confirm this address using the button below.</p>
<table role="presentation" align="center" cellpadding="0" cellspacing="0"><tr><td bgcolor="#0c4c8a" style="border-radius:9px"><a href="{{ $verifyUrl }}" style="display:inline-block;padding:14px 27px;color:#fff;text-decoration:none;font-size:14px;font-weight:bold">Verify Email Address</a></td></tr></table>
<p style="font-size:12px;color:#657991;line-height:1.6;margin:21px 0 0">This link expires in <strong>30 minutes</strong>.</p>
<p style="font-size:13px;color:#66788e;line-height:1.6;margin:23px 0 0">If you did not expect this email change, ignore this message. Your current verified email will remain active.</p>
</td></tr>
<tr><td style="padding:18px 27px;border-top:1px solid #e7ecf3;font-size:12px;color:#697d96;line-height:1.65">
If the button doesn't work, copy this link into your browser:<br>
<a href="{{ $verifyUrl }}" style="color:#0c4c8a;text-decoration:underline;overflow-wrap:anywhere;word-break:break-all">{{ $verifyUrl }}</a>
</td></tr>
<tr><td style="padding:15px 24px;text-align:center;font-size:11px;color:#7a8ba2;border-top:1px solid #e7ecf3">GCT Transport Services, Inc. · Account Security</td></tr>
</table>
</body>
</html>