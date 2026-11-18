
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Letting World</title>
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
            padding: 28px 20px;
            text-align: center;
        }

        header h1 { margin: 0; }

        main {
            max-width: 900px;
            margin: 32px auto;
            padding: 40px;
            background: white;
            border-radius: 14px;
        }

        h2 {
            color: #0B2A5B;
            margin-top: 30px;
        }

        a { color: #0B2A5B; }

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
    <p>Privacy Policy</p>
</header>

<main>
    <p><strong>Last Updated:</strong> 18 November 2026</p>

    <h2>1. Introduction</h2>
    <p>
        Welcome to Letting World. Our application helps
        landlords and tenants manage rental properties,
        tenancy information, certificates, documents,
        maintenance requests and related services.
    </p>
    <p>
        This Privacy Policy explains how personal
        information is collected, used, stored and
        protected when using the Letting World app.
    </p>

    <h2>2. Information We Collect</h2>
    <p>
        Depending on how you use the application,
        information processed may include:
    </p>
    <ul>
        <li>Full name and contact information.</li>
        <li>Email address and telephone number.</li>
        <li>Account details and authentication information.</li>
        <li>Property addresses and property information.</li>
        <li>Landlord and tenant details.</li>
        <li>Tenancy agreements and uploaded documents.</li>
        <li>Property certificates and expiry dates.</li>
        <li>Maintenance requests, notes and photographs.</li>
        <li>Service bookings and transaction records.</li>
        <li>Technical information needed to operate the app.</li>
    </ul>

    <h2>3. How We Use Your Information</h2>
    <p>We use relevant personal information to:</p>
    <ul>
        <li>Create and manage user accounts.</li>
        <li>Provide property management functionality.</li>
        <li>Manage landlord and tenant relationships.</li>
        <li>Store and display property documents.</li>
        <li>Manage certificates and maintenance requests.</li>
        <li>Support service bookings and payments.</li>
        <li>Respond to enquiries and support requests.</li>
        <li>Maintain application security and reliability.</li>
        <li>Comply with applicable legal obligations.</li>
    </ul>

    <h2>4. Payment Information</h2>
    <p>
        Letting World integrates with Stripe for supported
        payment functionality. Payment details entered
        through Stripe are processed using Stripe's
        payment services. We may retain relevant
        booking and payment records.
    </p>

    <h2>5. Sharing of Information</h2>
    <p>
        Information may be made available to authorised
        landlords and tenants according to their account
        permissions and assigned properties.
    </p>
    <p>
        We may also disclose information to relevant
        service providers where necessary to fulfil
        requested bookings, process payments or
        operate the application.
    </p>

    <h2>6. Data Security</h2>
    <p>
        We use appropriate technical and organisational
        measures intended to protect personal information
        against unauthorised access, alteration, loss
        and misuse.
    </p>

    <h2>7. Data Retention</h2>
    <p>
        Personal information is retained for as long
        as necessary to provide the services, fulfil
        legitimate operational needs and meet
        applicable legal requirements.
    </p>

    <h2>8. Your Privacy Rights</h2>
    <p>
        Subject to applicable UK data protection laws,
        you may have rights to request access to,
        correction of or deletion of your personal
        information, and to object to or restrict
        certain processing.
    </p>
    <p>
        You can contact us to exercise your rights
        or raise a privacy concern.
    </p>

    <h2>9. Account Deletion</h2>
    <p>
        Users may request deletion of their Letting
        World account and associated personal data,
        subject to applicable legal retention requirements.
    </p>
    <p>
        Please visit our
        <a href="{{ url('/account-deletion') }}">
            Account Deletion page
        </a>
        for further information.
    </p>

    <h2>10. Updates to This Policy</h2>
    <p>
        We may update this policy to reflect changes
        in the application, our practices or applicable
        legal requirements.
    </p>

    <h2>11. Contact Us</h2>
    <p>
        For privacy-related questions, contact:
    </p>
    <p>
        <strong>Email:</strong>
        <a href="mailto:info@lettingworld.com">
            info@lettingworld.com
        </a>
    </p>
</main>

<footer>
    &copy; {{ date('Y') }} Letting World.
    All rights reserved.
</footer>

</body>
</html>
