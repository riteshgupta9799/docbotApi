<!DOCTYPE html>
<html>
<head>
    <title>Thank You for Your Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #28a745;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Thank You for Your Order, {{ $customer_name }}!</h2>
        <p>We appreciate your purchase and are preparing your order.</p>

        <p><strong>Art name:</strong> {{ $art_name }}</p>
        <p><strong>Order Date:</strong> {{ $order_date }}</p>

        {{-- <p>You can track your order status or manage your order by clicking the button below:</p>
        <a href="{{ $order_url }}" class="btn">View Order</a> --}}

        {{-- <p>If you have any questions, feel free to contact our support team.</p> --}}


        <p>Best regards,</p>
    <p><strong>{{ env('MAIL_FROM_NAME') }}</strong></p>

        {{-- <div class="footer">

            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div> --}}
    </div>
</body>
</html>
