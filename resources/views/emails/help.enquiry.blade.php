<!DOCTYPE html>
<html>
<head>
    <title>New Art Inquiry Received</title>
</head>
<body>
    <p>Dear Admin,</p>

    <p>A new inquiry has been received for {{ $adminMailData['enquiryCategory'] }}. Below are the details:</p>

    <p><strong>Inquiry Details:</strong></p>
    <blockquote>
        "{{ $adminMailData['message'] }}"
    </blockquote>

    <p><strong>Customer Information:</strong></p>
    <p>Name: {{ $adminMailData['customer_name'] }}</p>
    <p>Email: <a href="mailto:{{ $adminMailData['customer_email'] }}">{{ $adminMailData['customer_email'] }}</a></p>

    {{-- <p><strong>Artist Information:</strong></p>
    <p>Name: {{ $adminMailData['artist_name'] }}</p>
    <p>Email: <a href="mailto:{{ $adminMailData['artist_email'] }}">{{ $adminMailData['artist_email'] }}</a></p> --}}

    {{-- <p><strong>Artwork Details:</strong></p>
    <p>Title: {{ $adminMailData['art_title'] }}</p>
    <p>Art ID: {{ $adminMailData['art_id'] }}</p> --}}

    <p>Please review and take any necessary action.</p>

    <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
    {{-- <p><a href="mailto:{{ env('MAIL_FROM_ADDRESS') }}">{{ env('MAIL_FROM_ADDRESS') }}</a></p> --}}
</body>
</html>
