<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#0d1b2a;font-family:Arial,Helvetica,sans-serif;color:#e8edf2;padding:32px">
  <div style="max-width:480px;margin:0 auto;background:#162032;border:1px solid #243548;border-radius:10px;overflow:hidden">
    <div style="height:3px;background:#c0201a"></div>
    <div style="padding:28px 30px">
      <div style="font-size:18px;font-weight:800;letter-spacing:-.02em">Prodmee Slate</div>
      <p style="color:#8a9bb0;font-size:14px;line-height:1.6;margin:18px 0 8px">
        Here is your sign-in code. Enter it on the login screen to continue.
      </p>
      <div style="font-size:34px;font-weight:800;letter-spacing:8px;color:#fff;background:#0d1b2a;border:1px solid #243548;border-radius:8px;text-align:center;padding:18px 0;margin:14px 0">
        {{ $code }}
      </div>
      <p style="color:#4a5a6a;font-size:12px;line-height:1.6;margin:8px 0 0">
        This code expires in {{ $ttl }} minutes and can be used once. If you didn't request it, you can ignore this email.
      </p>
    </div>
  </div>
</body>
</html>
