<!DOCTYPE html>
<html>
<head>
    <title>Artwork Approved</title>
</head>
<body>
    <p>Dear {{ $artistMailData['name'] }},</p>

    <p>Congratulations! Your artwork <strong>"{{ $artistMailData['art_title'] }}"</strong> has been successfully approved.</p>

    <p>{{ $artistMailData['message'] }}</p>

    <p>Your artwork is now live and can be viewed by potential buyers and art enthusiasts.</p>

    <p>If you have any questions, feel free to contact our support team.</p>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p>{{ env('MAIL_FROM_ADDRESS') }}</p> --}}
</body>
</html>
