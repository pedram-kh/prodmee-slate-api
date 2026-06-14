<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#0d1b2a;font-family:Arial,Helvetica,sans-serif;color:#e8edf2;padding:32px">
  <div style="max-width:480px;margin:0 auto;background:#162032;border:1px solid #243548;border-radius:10px;overflow:hidden">
    <div style="height:3px;background:#c0201a"></div>
    <div style="padding:28px 30px">
      <div style="font-size:18px;font-weight:800;letter-spacing:-.02em">Prodmee Slate</div>
      <p style="color:#8a9bb0;font-size:14px;line-height:1.6;margin:18px 0 8px">
        Hi {{ $name }}, you've been invited to join <strong style="color:#e8edf2">Prodmee Slate</strong> as {{ $roleLabel }}.
      </p>
      <p style="color:#8a9bb0;font-size:14px;line-height:1.6;margin:0 0 20px">
        To get started, open the login screen and enter this email address. You'll receive a one-time sign-in code &mdash; no password needed.
      </p>
      <a href="{{ $loginUrl }}" style="display:inline-block;background:#c0201a;color:#fff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 26px;border-radius:8px">
        Go to the login screen
      </a>
      <p style="color:#4a5a6a;font-size:12px;line-height:1.6;margin:22px 0 0">
        If the button doesn't work, copy and paste this link into your browser:<br>
        <span style="color:#6a7b8a">{{ $loginUrl }}</span>
      </p>
    </div>
  </div>
</body>
</html>
