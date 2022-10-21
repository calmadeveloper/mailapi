<?php

namespace Calmadeveloper\MailApi;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class MailApiTransport extends AbstractTransport
{
    /**
     * @var string
     */
    protected $apiKey = '';

    /**
     * @var string
     */
    protected $endpoint = '';

    /**
     * @var string
     */
    protected $connection = '';

    /**
     * @var string
     */
    protected $queue = '';

    /**
     * MailApiTransport constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->endpoint = $config['endpoint'];
        $this->connection = $config['connection'];
        $this->queue = $config['queue'];

        parent::__construct();
    }

    /**
     * @param SentMessage $message
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            $payload = [
                'header' => ['Content-Type', 'application/json'],
                'json' => [
                    'api_key' => $this->apiKey
                ]
            ];

            $this->addFrom($email, $payload);
            $this->addSubject($email, $payload);
            $this->addContent($email, $payload);
            $this->addRecipients($email, $payload);
            $this->addAttachments($email, $payload);

            dispatch(new MailApiJob($this->endpoint, $payload))
                ->onConnection($this->connection)
                ->onQueue($this->queue);

        } catch (\Exception $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param Email $email
     * @param $payload
     */
    protected function addFrom(Email $email, &$payload)
    {
        $from = collect($email->getFrom());

        if ($from->isNotEmpty()) {
            $payload['json']['from_email'] = $from->first()->getAddress();

            $fromName = $from->first()->getName() ?: null;
            if ($fromName) {
                $payload['json']['from_name'] = $fromName;
            }
        }
    }

    /**
     * @param Email $email
     * @param $payload
     */
    protected function addSubject(Email $email, &$payload)
    {
        $subject = $email->getSubject();

        if ($subject) {
            $payload['json']['subject'] = $subject;
        }
    }

    /**
     * @param Email $email
     * @param $payload
     */
    protected function addContent(Email $email, &$payload)
    {
        $body = $email->getHtmlBody();

        $payload['json']['html'] = $body;
    }

    /**
     * @param Email $email
     * @param $payload
     */
    protected function addRecipients(Email $email, &$payload)
    {
        foreach (['To', 'Cc', 'Bcc'] as $field) {
            $formatted = [];
            $method = 'get' . $field;
            $contacts = (array)$email->$method();
            foreach ($contacts as $address) {
                $formatted[] = $address->toString();
            }

            if (count($formatted) > 0) {
                $payload['json'][strtolower($field)] = implode(', ', $formatted);
            }
        }
    }

    /**
     * @param Email $email
     * @param $payload
     */
    protected function addAttachments(Email $email, &$payload)
    {
        $attachments = $email->getAttachments();

        if (count($attachments) > 0) {
            $payload['json']['attachments'] = array();

            foreach ($attachments as $attachment) {
                if (is_object($attachment) and $attachment instanceof DataPart) {
                    $a = array(
                        'filename' => $attachment->getPreparedHeaders()->get('content-disposition')->getParameter('filename'),
                        'content' => base64_encode($attachment->getBody()),
                        'content_type' => $attachment->getPreparedHeaders()->get('content-type')->getBody()
                    );
                    if (
                        $attachment->getPreparedHeaders()->get('content-disposition')->getBody() != 'attachment' &&
                        $attachment->getContentId() != NULL
                    ) {
                        $a['content_id'] = 'cid:' . $attachment->getContentId();
                    }
                    $payload['json']['attachments'][] = $a;
                }
            }
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return 'mailapi';
    }
}
