<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTOs\ContactMessageDTO;
use App\Application\Services\ContactServiceInterface;
use App\Http\Requests\ContactFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

final class ContactController extends Controller
{
    public function __construct(
        private readonly ContactServiceInterface $contactService,
    ) {}

    public function __invoke(ContactFormRequest $request): RedirectResponse
    {
        // Honeypot check - if website field has value, silently succeed but don't send email
        if ($request->isHoneypotFilled()) {
            return back()->with('success', __('contact.success'));
        }

        /** @var array{name: string, email: string, message: string} $validated */
        $validated = $request->validated();

        $sent = $this->contactService->sendContactMessage(
            ContactMessageDTO::fromArray($validated)
        );

        if (! $sent) {
            Log::warning('Contact form submitted but contact email is not configured.');

            return back()->with('error', __('contact.error'));
        }

        return back()->with('success', __('contact.success'));
    }
}
