{{--
Author: ragul-onemodo
Created: 2026-10-12 13:28:21 Asia/Calcutta (UTC+05:30)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MODO Mobile API · Swagger</title>
    <link rel="stylesheet" href="{{ l5_swagger_asset('default', 'swagger-ui.css') }}">
    <style>body{margin:0;background:#f8fafc;font-family:system-ui,sans-serif}.intro{background:#17283e;color:#fff;padding:20px 28px}.intro h1{font-size:22px;margin:0 0 8px}.intro p{margin:0;line-height:1.6;font-size:14px}.intro a{color:#99f6e4}.swagger-ui .topbar{display:none}</style>
</head>
<body>
    <header class="intro">
        <h1>MODO Mobile API</h1>
        <p>Grant mobile access in Membership → Users. Login with email/password or request and verify a WhatsApp OTP.<br>Copy <strong>data.token</strong> into <strong>Authorize</strong>, then enter <strong>X-Plant-Id</strong> for workspace requests. <a href="{{ route('sales-mobile.openapi') }}">Download OpenAPI JSON</a></p>
    </header>
    <div id="swagger-ui"></div>
    <script src="{{ l5_swagger_asset('default', 'swagger-ui-bundle.js') }}"></script>
    <script src="{{ l5_swagger_asset('default', 'swagger-ui-standalone-preset.js') }}"></script>
    <script>
        window.addEventListener('load', () => {
            SwaggerUIBundle({
                url: @json(route('sales-mobile.openapi')),
                dom_id: '#swagger-ui',
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                layout: 'StandaloneLayout',
                docExpansion: 'none',
                filter: true,
                persistAuthorization: false,
                validatorUrl: null,
                supportedSubmitMethods: ['get','post','put','patch','delete'],
            });
        });
    </script>
</body>
</html>