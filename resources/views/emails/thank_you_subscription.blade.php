<!DOCTYPE html>
<html>
<head>
    <title>Thank You for Subscribing!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: #28a745;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
        }
        .content {
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            Thank You for Subscribing!
        </div>
        <div class="content">
            <p>Dear Subscriber,</p>
            <p>Thank you for subscribing to our newsletter! We’re excited to have you with us.</p>


            <p>We’ll keep you updated with our latest news, promotions, and special offers. Stay tuned!</p>

            <p>Best Regards,<br><strong>MiraMonet Team</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} MIRAMONET | All Rights Reserved
        </div>
    </div>
</body>
</html>
