<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Factory as HttpFactory;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

class BrevoTransport extends AbstractTransport
{
    public function __construct(
        protected HttpFactory $http,
        #[SensitiveParameter] protected string $apiKey,
        protected string $endpoint = 'https://api.brevo.com/v3/smtp/email',
        protected int $timeout = 10,
        protected int $connectTimeout = 3,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new TransportException('Brevo email delivery is not configured.');
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders(['api-key' => $this->apiKey])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post($this->endpoint, $this->payload($message));
        } catch (Throwable $exception) {
            throw new TransportException(
                'The Brevo email service could not be reached.',
                0,
                $exception,
            );
        }

        if (! $response->successful()) {
            $reason = $response->json('message');
            $reason = is_string($reason) && $reason !== '' ? $reason : 'The request was rejected.';

            throw new TransportException(sprintf(
                'Brevo email delivery failed with HTTP %d: %s',
                $response->status(),
                $reason,
            ));
        }

        $messageId = $response->json('messageId');
        if (! is_string($messageId) || $messageId === '') {
            throw new TransportException('Brevo accepted the request without returning a message ID.');
        }

        $message->setMessageId($messageId);

        if ($message->getOriginalMessage() instanceof Message) {
            $message->getOriginalMessage()->getHeaders()->addTextHeader('X-Brevo-Message-ID', $messageId);
        }
    }

    private function payload(SentMessage $message): array
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? $message->getEnvelope()->getSender();

        return array_filter([
            'sender' => $this->address($from),
            'to' => $this->addresses($this->recipients($email, $message)),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => isset($email->getReplyTo()[0]) ? $this->address($email->getReplyTo()[0]) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
            'headers' => $this->headers($email),
            'attachment' => $this->attachments($email),
        ], static fn ($value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function recipients(Email $email, SentMessage $message): array
    {
        $copies = [...$email->getCc(), ...$email->getBcc()];

        return array_values(array_filter(
            $message->getEnvelope()->getRecipients(),
            static fn (Address $address): bool => ! in_array($address, $copies, true),
        ));
    }

    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], static fn (string $value): bool => $value !== '');
    }

    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => $this->address($address), $addresses);
    }

    private function headers(Email $email): array
    {
        $headers = [];
        $excluded = ['from', 'to', 'cc', 'bcc', 'reply-to', 'sender', 'subject', 'content-type'];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (! in_array($name, $excluded, true)) {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }

        return $headers;
    }

    private function attachments(Email $email): array
    {
        return array_map(static function ($attachment): array {
            $headers = $attachment->getPreparedHeaders();

            return [
                'content' => str_replace("\r\n", '', $attachment->bodyToString()),
                'name' => $headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment',
            ];
        }, $email->getAttachments());
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
