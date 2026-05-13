<?php

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('submits valid contact form and sends email', function () {
    Mail::fake();

    $payload = [
        'kind'    => 'consultoria',
        'name'    => 'Cliente Teste',
        'company' => 'Acme LTDA',
        'email'   => 'cliente@example.com',
        'budget'  => 'R$ 5–15k',
        'message' => 'Gostaria de discutir um projeto Filament para janeiro.',
    ];

    $response = $this->post('/pt/contato', $payload);

    $response->assertRedirect('/pt/contato');
    $response->assertSessionHas('contact_sent', true);

    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->hasTo('contato@jeffersongoncalves.dev.br')
            && $mail->payload['name'] === 'Cliente Teste';
    });
});

it('rejects invalid kind', function () {
    Mail::fake();

    $this->post('/pt/contato', [
        'kind'    => 'invalid',
        'name'    => 'X',
        'email'   => 'x@example.com',
        'message' => 'A short message that meets length.',
    ])->assertSessionHasErrors(['kind']);

    Mail::assertNothingSent();
});

it('rejects missing required fields', function () {
    Mail::fake();

    $this->post('/pt/contato', [
        'kind'    => 'consultoria',
    ])->assertSessionHasErrors(['name', 'email', 'message']);
});

it('rejects bad email', function () {
    $this->post('/pt/contato', [
        'kind'    => 'oss',
        'name'    => 'Test',
        'email'   => 'not-an-email',
        'message' => 'A message with enough chars.',
    ])->assertSessionHasErrors(['email']);
});

it('rejects short message', function () {
    $this->post('/pt/contato', [
        'kind'    => 'oss',
        'name'    => 'Test',
        'email'   => 'a@b.com',
        'message' => 'short',
    ])->assertSessionHasErrors(['message']);
});
