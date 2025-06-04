<!DOCTYPE html>
<html>
<head>
    <title>Art Declined</title>
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
            background: #d9534f;
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
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 20px;
        }
        .highlight {
            color: #d9534f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            Art Declined Notification
        </div>
        <div class="content">
            <p>Dear <strong>{{ $admin_name }}</strong>,</p>
            <p>We regret to inform you that the following art piece has been <span class="highlight">declined</span> by the seller.</p>

            <p><strong>Art Details:</strong></p>
            <ul>
                <li><strong>Title:</strong> {{ $art_name }}</li>
                <li><strong>Unique ID:</strong> {{ $art_unique_id }}</li>
                <li><strong>Status:</strong> {{ $status }}</li>
            </ul>

            <p><strong>Artist Details:</strong></p>
            <ul>
                <li><strong>Name:</strong> {{ $artist_name }}</li>
                <li><strong>Email:</strong> {{ $artist_email }}</li>
            </ul>

            <p><strong>Customer Details:</strong></p>
            <ul>
                <li><strong>Name:</strong> {{ $customer_name }}</li>
                <li><strong>Email:</strong> {{ $customer_email }}</li>
            </ul>

            <p>Please take necessary action as required.</p>

            <p>Best Regards,<br><strong>Your Team</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} MIRAMONET | All Rights Reserved
        </div>
    </div>
</body>
</html>
