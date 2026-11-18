
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion | Letting World</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #334155;
            font-family: Arial, sans-serif;
            line-height: 1.7;
        }

        header {
            background: #0B2A5B;
            color: white;
            text-align: center;
            padding: 28px 20px;
        }

        header h1 { margin: 0; }

        main {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 14px;
        }

        h2 {
            color: #0B2A5B;
            margin-top: 28px;
        }

        a {
            color: #0B2A5B;
            font-weight: bold;
        }

        .email {
            background: #f0f5ff;
            padding: 18px;
            border-radius: 8px;
            margin: 20px 0;
            overflow-wrap: anywhere;
        }

        footer {
            text-align: center;
            padding: 24px;
            font-size: 14px;
        }

        @media(max-width: 600px) {
            main {
                margin: 12px;
                padding: 24px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>Letting World</h1>
    <p>Account Deletion</p>
</header>

<main>
    <h2>Request Account Deletion</h2>

    <p>
        If you would like to delete your Letting World
        account, you can contact our support team
        using the email address below.
    </p>

    <div class="email">
        <strong>Email:</strong>
        <a href="mailto:info@lettingworld.com">
            info@lettingworld.com
        </a>
    </div>

    <h2>How to Request Deletion</h2>

    <p>
        Please send an email from the address
        associated with your Letting World account
        and include the subject:
    </p>

    <p>
        <strong>Letting World Account Deletion Request</strong>
    </p>

    <p>
        Our team will review your request,
        verify account ownership where necessary
        and provide further instructions.
    </p>

    <h2>What Happens to Your Data?</h2>

    <p>
        Following a verified request, we will process
        deletion of your account and associated
        personal information where applicable.
    </p>

    <p>
        Some records may be retained where required
        for legal, accounting or security purposes.
        We will explain any applicable retention.
    </p>

    <h2>Privacy Policy</h2>

    <p>
        For more information, please read our
        <a href="{{ url('/privacy-policy') }}">
            Privacy Policy
        </a>.
    </p>
</main>

<footer>
    &copy; {{ date('Y') }} Letting World.
    All rights reserved.
</footer>

</body>
</html>
