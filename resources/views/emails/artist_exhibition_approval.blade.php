<!DOCTYPE html>
<html>
<head>
    <title>Exhibition Approval</title>
</head>
<body>
    <p>Dear {{ $artistMailData['name'] }},</p>

    <p>Congratulations! Your artwork has been <strong>Approved</strong> for the exhibition.</p>

    <p><strong>Important Information:</strong></p>
    <blockquote>
        {{ $artistMailData['disclaimer'] }}
    </blockquote>

    <p>We are excited to have your art showcased in our exhibition. If you have any questions, feel free to reach out.</p>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p>{{ env('MAIL_FROM_ADDRESS') }}</p> --}}
</body>
</html>
