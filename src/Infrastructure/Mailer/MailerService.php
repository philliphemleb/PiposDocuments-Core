<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
    ) {
        if ('null://null' === $this->mailerDsn) {
            throw new RuntimeException('Mailer not configured. Add MAILER_DSN to environment.');
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function send(Email $email): void
    {
        $this->mailer->send($email);
    }
}
