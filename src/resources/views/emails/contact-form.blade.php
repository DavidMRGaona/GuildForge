<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('contact.email_subject') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0; text-align: center;">
                <table role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 20px 40px; text-align: center; background-color: #1f2937; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                {{ __('contact.email_subject') }}
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Sender Name -->
                            <table role="presentation" style="width: 100%; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <strong style="color: #374151; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Nombre:
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 20px;">
                                        <p style="margin: 0; color: #1f2937; font-size: 16px;">
                                            {{ $senderName }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Sender Email -->
                            <table role="presentation" style="width: 100%; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <strong style="color: #374151; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Email:
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 20px;">
                                        <a href="mailto:{{ $senderEmail }}" style="color: #3b82f6; text-decoration: none; font-size: 16px;">
                                            {{ $senderEmail }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Message -->
                            <table role="presentation" style="width: 100%;">
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <strong style="color: #374151; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Mensaje:
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 20px; background-color: #f9fafb; border-left: 4px solid #3b82f6; border-radius: 4px;">
                                        <p style="margin: 0; color: #1f2937; font-size: 16px; line-height: 1.6; white-space: pre-wrap;">{{ $messageBody }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 40px; text-align: center; background-color: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">
                                Este mensaje fue enviado desde el formulario de contacto de Runesword
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
