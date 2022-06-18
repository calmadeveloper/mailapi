<?php

namespace Calmadeveloper\MailApi;

use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;
use Swift_TransportException;

class MailApiTransport extends Transport
{
    /**
     * Guzzle HTTP client.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * The Mailjet "API key" which can be found at https://app.mailjet.com/transactional
     *
     * @var string
     */
    protected $apiKey;

    /**
     * The Mailjet end point we're using to send the message.
     *
     * @var string
     */
    protected $endpoint = '';

    /**
     * Create a new Mailjet transport instance.
     *
     * @param \GuzzleHttp\ClientInterface $client
     * @param $apiKey
     * @param $endpoint
     */
    public function __construct(ClientInterface $client, $apiKey, $endpoint)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param null $failedRecipients
     * @return int
     * @throws Swift_TransportException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->beforeSendPerformed($message);

        try {
            $payload = [
                'header' => ['Content-Type', 'application/json'],
                'json' => [
                    'api_key' => $this->apiKey
                ]
            ];

            $this->addFrom($message, $payload);
            $this->addSubject($message, $payload);
            $this->addContent($message, $payload);
            $this->addRecipients($message, $payload);
            $this->addAttachments($message, $payload);

            $response = $this->client->request('POST', $this->endpoint, $payload);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception($response->getBody()->getContents());
            }
        } catch (\Exception $e) {
            throw new Swift_TransportException('Request to '. $this->endpoint .' API failed.', $e->getCode(), $e);
        }

        $resultArray = json_decode($response->getBody()->getContents(), true);

        $message->getHeaders()->addTextHeader('X-Message-ID', $resultArray['message_id']);
        $message->getHeaders()->addTextHeader('X-SES-Message-ID', $resultArray['message_id']);

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param $payload
     */
    protected function addFrom(Swift_Mime_SimpleMessage $message, &$payload)
    {
        $from = $message->getFrom();

        $fromAddress = key($from);
        if ($fromAddress) {
            $payload['json']['from_email'] = $fromAddress;

            $fromName = $from[$fromAddress] ?: null;
            if ($fromName) {
                $payload['json']['from_name'] = $fromName;
            }
        }
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param $payload
     */
    protected function addAttachments(Swift_Mime_SimpleMessage $message, &$payload)
    {
        if ($message->getChildren()) {
            $payload['json']['attachments'] = array();
            foreach ($message->getChildren() as $attachment) {
                if (is_object($attachment) and $attachment instanceof \Swift_Mime_Attachment) {
                    $a = array(
                        'filename' => $attachment->getFilename(),
                        'content' => base64_encode($attachment->getBody()),
                        'content_type' => $attachment->getContentType()
                    );
                    if ($attachment->getDisposition() != 'attachment' && $attachment->getId() != NULL) {
                        $a['content_id'] = 'cid:' . $attachment->getId();
                    }
                    $payload['json']['attachments'][] = $a;
                }
            }
        }
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param $payload
     */
    protected function addSubject(Swift_Mime_SimpleMessage $message, &$payload)
    {
        $subject = $message->getSubject();
        if ($subject) {
            $payload['json']['subject'] = $subject;
        }
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param $payload
     */
    protected function addContent(Swift_Mime_SimpleMessage $message, &$payload)
    {
        $contentType = $message->getContentType();
        $body = $message->getBody();

        if (!in_array($contentType, ['text/html', 'text/plain'])) {
            $contentType = strip_tags($body) != $body ? 'text/html' : 'text/plain';
        }

        $payload['json'][$contentType == 'text/html' ? 'html' : 'text'] = $message->getBody();
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param $payload
     */
    protected function addRecipients(Swift_Mime_SimpleMessage $message, &$payload)
    {
        foreach (['To', 'Cc', 'Bcc'] as $field) {
            $formatted = [];
            $method = 'get' . $field;
            $contacts = (array)$message->$method();
            foreach ($contacts as $address => $display) {
                $formatted[] = $display ? $display . " <$address>" : $address;
            }

            if (count($formatted) > 0) {
                $payload['json'][strtolower($field)] = implode(', ', $formatted);
            }
        }
    }
}
