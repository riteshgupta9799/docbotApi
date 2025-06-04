<!DOCTYPE html>
<html>
<head>
    <title>Wallet Request Status</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
        <h2 style="color: #333;">Hello {{ $artistmailData['name'] }},</h2>

        <p>We are pleased to inform you that your wallet request has been
            <strong style="color: {{ $artistmailData['status'] == 'Approved' ? 'green' : 'red' }};">
                {{ ucfirst($artistmailData['status']) }}
            </strong>.
        </p>

        @if(isset($artistmailData['amount']))
        <p><strong>Requested Amount:</strong> ${{ number_format($artistmailData['amount'], 2) }}</p>
        @endif

        <p>You can check your wallet status in your account.</p>

        <p>If you have any questions, feel free to contact our support team.</p>

        <p>Best Regards,</p>
        <p><strong>{{ env('MAIL_FROM_NAME', 'Your Application Name') }}</strong></p>
    </div>
</body>
</html>
