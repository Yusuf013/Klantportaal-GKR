<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test het antwoordadres (Reply-To) van de mails uit het portaal.
 * Er wordt niets echt verstuurd: in tests gebruikt Laravel een nep-postbus ("array").
 */
class MailReplyToTest extends TestCase
{
    /**
     * Stuurt een testmail met dit antwoordadres in de instellingen en geeft
     * de antwoordadressen terug die in de verstuurde mail staan.
     */
    private function replyToAddressesWith(?string $address): array
    {
        // Hier vast instellen, zodat de test niet afhangt van wat er in jouw .env staat
        config(['mail.reply_to' => ['address' => $address, 'name' => 'GKR Digital Agency']]);

        // Laravel onthoudt de mailinstellingen: opnieuw laten opbouwen met de regel hierboven
        Mail::forgetMailers();

        Mail::raw('Testbericht', fn ($message) => $message->to('klant@example.com')->subject('Test'));

        $sent = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);

        return array_map(
            fn ($replyTo) => $replyTo->getAddress(),
            $sent->first()->getOriginalMessage()->getReplyTo()
        );
    }

    public function test_instellingen_hebben_een_plek_voor_het_antwoordadres(): void
    {
        $this->assertIsArray(config('mail.reply_to'));
        $this->assertArrayHasKey('address', config('mail.reply_to'));
        $this->assertArrayHasKey('name', config('mail.reply_to'));
    }

    public function test_mail_krijgt_het_ingestelde_antwoordadres(): void
    {
        $this->assertSame(['info@gkr.nl'], $this->replyToAddressesWith('info@gkr.nl'));
    }

    public function test_zonder_instelling_heeft_de_mail_geen_antwoordadres(): void
    {
        $this->assertSame([], $this->replyToAddressesWith(null));
    }
}