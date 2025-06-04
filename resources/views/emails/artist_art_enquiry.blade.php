<!DOCTYPE html>
<html>
<head>
    <title>New Art Inquiry</title>
</head>
<body>
    <p>Dear {{ $artistmailData['name'] }},</p>

    <p>You have received a new inquiry regarding your artwork. Please find the details below:</p>

    <p><strong>Inquiry Details:</strong></p>
    <blockquote>
        "{{ $artistmailData['message'] }}"
    </blockquote>

    <p><strong>Customer Information:</strong></p>
    <p>Name: {{ $artistmailData['customer_name'] }}</p>
    {{-- <p>Email: <a href="mailto:{{ $artistmailData['customer_email'] }}">{{ $artistmailData['customer_email'] }}</a></p> --}}

    <p>Please respond to the customer at your earliest convenience.</p>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p><a href="mailto:{{ env('MAIL_FROM_ADDRESS') }}">{{ env('MAIL_FROM_ADDRESS') }}</a></p> --}}
</body>
</html>
