<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Google sign-in stays hidden, and never reaches Google's error page, until it's set up. */
class GoogleSignInTest extends TestCase
{
    public function test_not_set_up_hides_the_button_and_sends_a_clear_message_back_to_the_app(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->getJson('/api/auth/google/status')->assertOk()->assertExactJson(['enabled' => false]);

        $location = $this->get('/api/auth/google/redirect?redirect_uri='.urlencode('lettingworld://auth/callback'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('lettingworld://auth/callback?error=', $location);
        $this->assertStringNotContainsString('accounts.google.com', $location);
    }

    public function test_set_up_shows_the_button_and_goes_to_google(): void
    {
        config(['services.google.client_id' => 'client-id.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);

        $this->getJson('/api/auth/google/status')->assertOk()->assertExactJson(['enabled' => true]);

        $location = $this->get('/api/auth/google/redirect?redirect_uri='.urlencode('lettingworld://auth/callback'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('accounts.google.com', $location);
        $this->assertStringContainsString('client_id=client-id.apps.googleusercontent.com', $location);
    }
}
