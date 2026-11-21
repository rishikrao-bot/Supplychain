<!doctype html>
<html lang="en-GB"><body style="font-family:Arial,sans-serif;color:#1E3440;line-height:1.5">
<p>Hello {{ $user->name }},</p>
<p>{{ $invitedBy ? $invitedBy.' has' : config('portal.company').' has' }} invited you to the {{ config('portal.name') }} as <b>{{ $user->roleLabel() }}</b> at {{ $user->organisation->name }}.</p>
<p><a href="{{ $link }}" style="display:inline-block;background:#25C4A9;color:#14303A;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:bold">Set up your account</a></p>
<p>The link works once and expires in 7 days. You'll choose a password and set up two-step login with an authenticator app on your phone.</p>
<p>If you weren't expecting this, you can ignore this email.</p>
<p>{{ config('portal.company') }}</p>
</body></html>
