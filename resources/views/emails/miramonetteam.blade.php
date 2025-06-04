<!DOCTYPE html>
<html>
<head>
    <title>MIRAMONET Inquiry</title>
</head>
<body>
    <p>Dear  {{ $adminMailData['admin_name'] }},</p>

    <p>You have received a new Miramonet Team inquiry.</p>

    <p><strong>Customer Name:</strong> {{ $adminMailData['customer_name'] }}</p>
    <p><strong>Customer Email:</strong> {{ $adminMailData['customer_email'] }}</p>

    <p><strong>Message:</strong></p>
    <blockquote>
        {{ $adminMailData['message'] }}
    </blockquote>

    <p>Please review the inquiry and respond accordingly.</p>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p>{{ env('MAIL_FROM_ADDRESS') }}</p> --}}
</body>
</html>
