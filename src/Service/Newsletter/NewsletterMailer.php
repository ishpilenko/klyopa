<?php
declare(strict_types=1);
namespace App\Service\Newsletter;

use App\Entity\NewsletterIssue;
use App\Entity\NewsletterSubscriber;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NewsletterMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        $site = $subscriber->getSite();

        $confirmUrl = $this->urlGenerator->generate(
            'newsletter_confirm',
            ['token' => $subscriber->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@' . $site->getDomain(), $site->getName()))
            ->to($subscriber->getEmail())
            ->subject('Confirm your subscription to ' . $site->getName())
            ->htmlTemplate('email/newsletter_confirmation.html.twig')
            ->context(['subscriber' => $subscriber, 'site' => $site, 'confirm_url' => $confirmUrl]);

        $this->mailer->send($email);
    }

    public function sendIssue(NewsletterIssue $issue, NewsletterSubscriber $subscriber): void
    {
        $site = $issue->getSite();

        $unsubscribeUrl = $this->urlGenerator->generate(
            'newsletter_unsubscribe',
            ['token' => $subscriber->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $html = str_replace('{{UNSUBSCRIBE_URL}}', $unsubscribeUrl, $issue->getContentHtml());

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@' . $site->getDomain(), $site->getName()))
            ->to($subscriber->getEmail())
            ->subject($issue->getSubject())
            ->html($html);

        $email->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->mailer->send($email);
    }
}
