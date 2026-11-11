<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verify your email address</title>
    </head>
    <body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5; padding: 24px;">
        <h1 style="font-size: 20px; margin: 0 0 12px;">Verify your email address</h1>

        <p style="margin: 0 0 16px;">
            Use the code below to verify your email address. It expires in {{ $expiresInMinutes }} minutes.
        </p>

        <p style="font-size: 30px; font-weight: bold; letter-spacing: 8px; margin: 0 0 16px;">
            {{ $code }}
        </p>

        <p style="margin: 0; color: #6b7280;">
            If you did not request this change, you can ignore this email.
        </p>
    </body>
</html>
