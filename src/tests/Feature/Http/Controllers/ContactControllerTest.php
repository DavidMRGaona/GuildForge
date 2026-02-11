<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use App\Mail\ContactFormMail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ContactControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Verify successful contact form submission sends email with correct data.
     */
    public function test_contact_form_submission_sends_email(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'I would like to join your guild.',
            'website' => '', // Honeypot should be empty
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail) use ($formData): bool {
            return $mail->hasTo('info@guildforge.es')
                && $mail->senderName === $formData['name']
                && $mail->senderEmail === $formData['email']
                && $mail->messageBody === $formData['message'];
        });
    }

    /**
     * Verify the contact form requires a name field.
     */
    public function test_contact_form_requires_name(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'email' => 'john@example.com',
            'message' => 'Test message',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('name');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify the contact form requires a valid email address.
     */
    public function test_contact_form_requires_valid_email(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'invalid-email-format',
            'message' => 'Test message',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('email');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify the contact form requires a message field.
     */
    public function test_contact_form_requires_message(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('message');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify honeypot field rejects bot submissions silently.
     *
     * When the hidden "website" field has a value, it indicates a bot.
     * The form should appear to succeed (200 response) but not send email.
     */
    public function test_contact_form_honeypot_rejects_bots(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'Bot Name',
            'email' => 'bot@example.com',
            'message' => 'Spam message',
            'website' => 'https://spam-site.com', // Bot filled honeypot
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify rate limiting prevents spam submissions.
     *
     * After 3 submissions from the same IP within a minute,
     * the 4th submission should return 429 Too Many Requests.
     */
    public function test_contact_form_rate_limiting(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Test message',
            'website' => '',
        ];

        // Act & Assert
        // First 3 submissions should succeed
        for ($i = 0; $i < 3; $i++) {
            $response = $this->post(route('contact.store'), $formData);
            $response->assertRedirect();
        }

        // 4th submission should be rate limited
        $response = $this->post(route('contact.store'), $formData);
        $response->assertStatus(429);
    }

    /**
     * Verify successful submission redirects back with success flash message.
     */
    public function test_contact_form_redirects_with_success_flash(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'I would like to join your guild.',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();
    }

    /**
     * Verify contact form requires email field to be present.
     */
    public function test_contact_form_requires_email_field(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'message' => 'Test message',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('email');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify name field must be a string.
     */
    public function test_contact_form_name_must_be_string(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => ['invalid' => 'array'],
            'email' => 'john@example.com',
            'message' => 'Test message',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('name');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify message field must be a string.
     */
    public function test_contact_form_message_must_be_string(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => ['invalid' => 'array'],
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('message');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify name field has a maximum length.
     */
    public function test_contact_form_name_has_maximum_length(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => str_repeat('a', 256), // Assuming 255 max
            'email' => 'john@example.com',
            'message' => 'Test message',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('name');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify message field has a maximum length.
     */
    public function test_contact_form_message_has_maximum_length(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => str_repeat('a', 5001), // Assuming 5000 max
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertSessionHasErrors('message');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify contact form returns error when contact email is not configured.
     */
    public function test_contact_form_returns_error_when_email_not_configured(): void
    {
        // Arrange
        Mail::fake();
        // Do NOT set contact_email - leave it unconfigured

        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'I would like to join your guild.',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');
        Mail::assertNotSent(ContactFormMail::class);
    }

    /**
     * Verify email with special characters is validated correctly.
     */
    public function test_contact_form_validates_email_with_special_characters(): void
    {
        // Arrange
        Mail::fake();
        app(SettingsServiceInterface::class)->set('contact_email', 'info@guildforge.es');

        $formData = [
            'name' => 'John Doe',
            'email' => 'john.doe+test@example.com', // Valid email with special chars
            'message' => 'Test message',
            'website' => '',
        ];

        // Act
        $response = $this->post(route('contact.store'), $formData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        Mail::assertSent(ContactFormMail::class);
    }
}
