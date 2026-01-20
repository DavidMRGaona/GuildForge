<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

final class ContactController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Honeypot check - if website field has value, silently succeed but don't send email
        if ($request->filled('website')) {
            return back()->with('success', __('contact.success'));
        }

        // Validate the request
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Get recipient email from settings
        $settings = app(SettingsServiceInterface::class);
        $contactEmail = $settings->get('contact_email', '');

        // Only send email if contact_email is configured
        if ($contactEmail !== '') {
            Mail::to($contactEmail)->send(new ContactFormMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                messageBody: $validated['message'],
            ));
        }

        return back()->with('success', __('contact.success'));
    }
}
