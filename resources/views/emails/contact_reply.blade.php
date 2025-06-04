<!DOCTYPE html>
<html>
<head>
    <title>Response to Your Inquiry</title>
</head>
<body>
    <p>Dear {{ $mailData['name'] }},</p>

    <p>Thank you for reaching out to us. We have received your inquiry and would like to provide the following response:</p>

    <p><strong>Your Inquiry:</strong></p>
    <blockquote>
        {{ $mailData['message'] }}
    </blockquote>

    <p><strong>Our Response:</strong></p>
    <p>{{ $mailData['reply'] }}</p>

    {{-- <p>If you have any further questions, feel free to reply to this email.</p> --}}

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p>{{ env('MAIL_FROM_ADDRESS') }}</p> --}}
</body>
</html>
