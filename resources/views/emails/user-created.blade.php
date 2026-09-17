<!-- backend/resources/views/emails/user-created.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to TEMU</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f6f8;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background-color: #16233F;
            padding: 30px 40px;
            text-align: center;
        }
        .header h1 {
            color: #F0B429;
            font-size: 28px;
            margin: 0;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .header p {
            color: #A9B3C9;
            margin: 8px 0 0;
            font-size: 14px;
        }
        .content {
            padding: 40px;
        }
        .content h2 {
            color: #16233F;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .content p {
            color: #4b5563;
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 16px;
        }
        .user-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #F0B429;
        }
        .user-details .label {
            color: #64748B;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .user-details .value {
            color: #1F2937;
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .user-details .value:last-child {
            margin-bottom: 0;
        }
        .password-box {
            background-color: #FBF1DC;
            border: 1px solid #F0B429;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
            text-align: center;
        }
        .password-box .password {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: bold;
            color: #16233F;
            letter-spacing: 2px;
        }
        .password-box .label {
            color: #92600A;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .btn {
            display: inline-block;
            background-color: #1E8449;
            color: #ffffff;
            padding: 12px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            margin-top: 12px;
        }
        .btn:hover {
            background-color: #186B3B;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 40px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            color: #9ca3af;
            font-size: 12px;
            margin: 0;
        }
        .warning {
            color: #C8202F;
            font-size: 13px;
            margin-top: 12px;
            padding: 12px;
            background-color: #FBE7E9;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚦 TEMU</h1>
            <p>Traffic Enforcement and Management Unit</p>
            <p style="color: #C7CEDB; font-size: 12px;">El Salvador City</p>
        </div>

        <div class="content">
            <h2>Welcome to TEMU, {{ $user->firstname }}!</h2>

            <p>Your account has been successfully created in the TEMU Traffic Enforcement System.</p>

            <div class="user-details">
                <div class="label">👤 Full Name</div>
                <div class="value">{{ $user->firstname }} {{ $user->middlename }} {{ $user->lastname }}</div>

                <div class="label">📧 Email Address</div>
                <div class="value">{{ $user->email }}</div>

                <div class="label">🔑 Role</div>
                <div class="value">{{ ucfirst($user->role) }}</div>
            </div>

            <div class="password-box">
                <div class="label">🔐 Your Temporary Password</div>
                <div class="password">{{ $password }}</div>
            </div>

            <p style="font-size: 14px; color: #64748B;">
                <strong>⚠️ Please keep this password secure.</strong>
                You will be prompted to change it after your first login.
            </p>

            <div class="warning">
                ⚠️ If you did not request this account, please contact the TEMU administrator immediately.
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} TEMU - Traffic Enforcement and Management Unit</p>
            <p>El Salvador City, Philippines</p>
            <p style="font-size: 11px; color: #d1d5db;">This is an automated message. Please do not reply.</p>
        </div>
    </div>
</body>
</html>