<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Access Credentials</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #334155;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f1f5f9;
            padding: 40px 0;
        }
        .main-card {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 36px 32px;
            text-align: center;
        }
        .header-brand {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .header-brand span {
            color: #f59e0b;
        }
        .header-subtitle {
            font-size: 13px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 6px;
            font-weight: 600;
        }
        .body-content {
            padding: 36px 32px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 12px;
        }
        .text-p {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin: 0 0 24px 0;
        }
        .credentials-box {
            background-color: #f8fafc;
            border-left: 4px solid #4f46e5;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 28px;
            border-top: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .credential-row {
            margin-bottom: 14px;
        }
        .credential-row:last-child {
            margin-bottom: 0;
        }
        .credential-label {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }
        .credential-value {
            font-size: 16px;
            color: #0f172a;
            font-weight: 700;
            word-break: break-all;
        }
        .badge-password {
            display: inline-block;
            background-color: #eef2ff;
            color: #4338ca;
            font-family: 'Courier New', Courier, monospace;
            font-size: 18px;
            font-weight: 800;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px dashed #818cf8;
            letter-spacing: 1px;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            color: #ffffff !important;
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            padding: 14px 36px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: all 0.2s ease;
        }
        .notice-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 28px;
        }
        .notice-text {
            font-size: 13px;
            color: #92400e;
            line-height: 1.5;
            margin: 0;
        }
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 32px 0;
        }
        .company-address {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .address-text {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }
        .terms-box {
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.6;
            text-align: justify;
        }
        .terms-title {
            font-weight: 700;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }
        .footer-copyright {
            text-align: center;
            padding: 24px 32px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
        .footer-copyright span {
            color: #f59e0b;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <!-- Header -->
            <div class="header">
                <h1 class="header-brand">MODO <span>RMC</span></h1>
                <div class="header-subtitle">Plant Initialization & Access Credentials</div>
            </div>

            <!-- Body -->
            <div class="body-content">
                <h2 class="greeting">Welcome to the Portal!</h2>
                <p class="text-p">
                    Your administrator account for <strong>{{ $plant->name }}</strong> has been successfully created. You now have full access to manage your plant operations, dispatches, and reports.
                </p>

                <!-- Credentials Box -->
                <div class="credentials-box">
                    <div class="credential-row">
                        <span class="credential-label">Plant Name</span>
                        <div class="credential-value">{{ $plant->name }}</div>
                    </div>
                    
                    <div class="credential-row">
                        <span class="credential-label">Login Email Address</span>
                        <div class="credential-value"><strong>{{ $plant->email_address }}</strong></div>
                    </div>

                    <div class="credential-row">
                        <span class="credential-label">Temporary Password</span>
                        <div style="margin-top: 4px;">
                            <span class="badge-password"><strong>{{ $password }}</strong></span>
                        </div>
                    </div>

                    <div class="credential-row" style="margin-top: 16px;">
                        <span class="credential-label">Portal URL</span>
                        <div class="credential-value">
                            <a href="{{ $loginUrl }}" style="color: #4f46e5; text-decoration: underline;"><strong>{{ $loginUrl }}</strong></a>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="btn-container">
                    <a href="{{ $loginUrl }}" class="btn-primary">Login to Portal &rarr;</a>
                </div>

                <!-- Security Note -->
                <div class="notice-box">
                    <p class="notice-text">
                        🔒 <strong>Security Advice:</strong> Please log in using the credentials provided above and update your password immediately from your account settings for enhanced security.
                    </p>
                </div>

                <div class="divider"></div>

                <!-- OneModo Technologies Address -->
                <div class="company-address">
                    <div class="company-name">OneModo Technologies Private Limited</div>
                    <p class="address-text">
                        HQ: 7A 7th Floor, Century Plaza, Anna Salai, Thiru Vi Ka Kudiyiruppu, Teynampet, Chennai, Greater Chennai, Tamil Nadu 600018<br>
                        Website: <a href="https://onemodo.com" style="color: #4f46e5; text-decoration: none;">www.onemodo.com</a> | Email: <a href="mailto:chithra@onemodo.com" style="color: #4f46e5; text-decoration: none;">chithra@onemodo.com</a>
                    </p>
                </div>

                <!-- Terms and Conditions -->
                <div class="terms-box">
                    <span class="terms-title">Terms & Conditions / Confidentiality Notice:</span>
                    This automated email contains confidential credentials intended exclusively for the authorized administrator of <strong>{{ $plant->name }}</strong>. Any unauthorized review, use, disclosure, or distribution of this communication is strictly prohibited. Access to and usage of the Modo RMC platform are strictly governed by the standard Terms of Service and Privacy Policy of OneModo Technologies Private Limited.
                </div>
            </div>

            <!-- Footer -->
            <div class="footer-copyright">
                Powered by <span>OneModo Technologies</span> &bull; &copy; {{ date('Y') }} Modo RMC. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>
