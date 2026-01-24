<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

final class ContactController extends Controller
{
    public function __construct(
        private readonly SettingsServiceInterface $settings,
    ) {}

    public function __invoke(ContactFormRequest $request): RedirectResponse
    {
        // Honeypot check - if website field has value, silently succeed but don't send email
        if ($request->isHoneypotFilled()) {
            return back()->with('success', __('contact.success'));
        }

        $validated = $request->validated();

        // Get recipient email from settings
        $contactEmail = $this->settings->get('contact_email', '');

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
