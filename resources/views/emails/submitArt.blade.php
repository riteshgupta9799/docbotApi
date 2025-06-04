<!DOCTYPE html>
<html>
<head>
    <title>Artwork Upload Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            background: #ffffff;
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: #007bff;
            color: #ffffff;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
        }
        .content {
            padding: 20px;
            color: #333333;
            line-height: 1.6;
        }
        .art-image {
            text-align: center;
            margin-top: 15px;
        }
        .art-image img {
            max-width: 100%;
            border-radius: 5px;
            box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.1);
        }
        .footer {
            text-align: center;
            font-size: 14px;
            color: #666666;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #dddddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
             Artwork Upload Successful!
        </div>

        <div class="content">
            <p>Dear <strong>{{ $user_name }}</strong>,</p>

            <p>Congratulations! Your artwork titled <strong>"{{ $art_title }}"</strong> has been successfully uploaded.</p>

            @if($image)
                <div class="art-image">
                    <img src="{{ $image }}" alt="Artwork Preview">
                </div>
            @endif

            <p>Our team will review your submission, and you will be notified once it is approved.</p>

            <p>Thank you for sharing your creativity with us!</p>
        </div>

        <div class="footer">
            Best Regards, <br>
            <strong>Miramonet Team</strong>
        </div>
    </div>
</body>
</html>
