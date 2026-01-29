<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTOs\ContactMessageDTO;
use App\Application\Services\ContactServiceInterface;
use App\Http\Requests\ContactFormRequest;
use Illuminate\Http\RedirectResponse;

final class ContactController extends Controller
{
    public function __construct(
        private readonly ContactServiceInterface $contactService,
    ) {
    }

    public function __invoke(ContactFormRequest $request): RedirectResponse
    {
        // Honeypot check - if website field has value, silently succeed but don't send email
        if ($request->isHoneypotFilled()) {
            return back()->with('success', __('contact.success'));
        }

        /** @var array{name: string, email: string, message: string} $validated */
        $validated = $request->validated();

        $this->contactService->sendContactMessage(
            ContactMessageDTO::fromArray($validated)
        );

        return back()->with('success', __('contact.success'));
    }
}
