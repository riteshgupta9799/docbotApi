<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Return & Refund Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #4CAF50;">Return & Refund Confirmation</h2>

        <p>Dear {{ $customerData['customer_name'] }},</p>

        <p>We have processed your return request for <strong>Order #{{ $customerData['art_name'] }}</strong>.</p>

        <p>The refund amount of <strong>${{ number_format($customerData['refund_amount'], 2) }}</strong> has been initiated and should reflect in your account within 5-7 business days.</p>

        <p>If you have any further questions, feel free to contact our support team.</p>

        <p>Thank you for shopping with us.</p>

        <p>Best regards,</p>
        <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>
        <p><a href="{{ env('APP_URL') }}" style="color: #4CAF50; text-decoration: none;">Visit Our Website</a></p>
    </div>
</body>
</html>
