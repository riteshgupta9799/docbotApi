<!DOCTYPE html>
<html>
<head>
    <title>Artwork Declined</title>
</head>
<body>
    <p>Dear {{ $artistMailData['name'] }},</p>

    <p>We regret to inform you that your artwork submission <strong>"{{ $artistMailData['art_title'] }}"</strong> has been declined.</p>

    <p><strong>Reason:</strong> {{ $artistMailData['reason'] }}</p>

    <p>If you have any questions or need further details, please reach out to our support team.</p>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p>{{ env('MAIL_FROM_ADDRESS') }}</p> --}}
</body>
</html>
