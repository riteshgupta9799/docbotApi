<!DOCTYPE html>
<html>
<head>
    <title>Art Inquiry</title>
</head>
<body>
    <p>Dear {{ $mailData['name'] }},</p>

    <p>Thank you for reaching out to us. We have received your inquiry and will review it shortly. Our team will get back to you as soon as possible with a response.</p>

    <p><strong>Your  Art Inquiry:</strong></p>
    <blockquote>
        {{ $mailData['message'] }}
    </blockquote>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p>{{ env('MAIL_FROM_ADDRESS') }}</p> --}}
</body>
</html>
