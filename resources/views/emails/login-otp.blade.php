<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('Your IRIS login code') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f2;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f2;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #dfe7e1;">

                {{-- Header with logos --}}
                <tr>
                    <td align="center" style="padding:28px 24px 12px;">
                        <img src="{{ $message->embed(public_path('images/nia-logo.png')) }}" alt="National Irrigation Administration" width="72" height="72" style="display:inline-block;vertical-align:middle;border:0;">
                        <img src="{{ $message->embed(public_path('images/iris-logo.png')) }}" alt="IRIS Region V" width="230" style="display:inline-block;vertical-align:middle;border:0;margin-left:8px;">
                    </td>
                </tr>
                <tr><td style="height:4px;background:linear-gradient(90deg,#166a1e,#f5c400);font-size:0;line-height:0;">&nbsp;</td></tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <h1 style="margin:0 0 12px;font-size:20px;color:#111827;">{{ __('Hello :name,', ['name' => $name]) }}</h1>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                            {{ __('Use this code to finish signing in to IRIS:') }}
                        </p>

                        <div style="text-align:center;margin:0 0 20px;">
                            <span style="display:inline-block;background-color:#eef7ef;border:1px solid #b7d9bb;border-radius:10px;padding:14px 28px;font-size:32px;font-weight:700;letter-spacing:10px;color:#166a1e;font-family:'Courier New',monospace;">{{ $code }}</span>
                        </div>

                        <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4b5563;">
                            {{ __('This code expires in :m minutes. Do not share it with anyone.', ['m' => $minutes]) }}
                        </p>
                        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4b5563;">
                            {{ __('If you did not try to log in, change your password right away.') }}
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:20px 32px 28px;border-top:1px solid #eef2ef;">
                        <p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;">
                            &copy; {{ date('Y') }} IRIS. {{ __('Irrigators\' Registry and Information System') }}<br>
                            {{ __('National Irrigation Administration, Region V') }} &middot; {{ __('MDOO') }} <br>
                            {{ __('This is an automated message, please do not reply.') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
