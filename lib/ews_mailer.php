<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/ews_client.php';

/** PHPMailer builds the existing templates and attachments; EWS delivers the MIME message. */
final class EwsMailer extends PHPMailer
{
    public function __construct(private EwsClient $client, bool $exceptions = true)
    {
        parent::__construct($exceptions);
        // Use SMTP-style MIME headers, then deliver through HTTPS instead of postSend().
        $this->isSMTP();
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            $this->client->sendMime(
                $this->getSentMIMEMessage(),
                $this->getToAddresses(),
                $this->getCcAddresses(),
                $this->getBccAddresses()
            );
            return true;
        } catch (Throwable $error) {
            $this->setError($error->getMessage());
            if ($this->exceptions) {
                throw new Exception($error->getMessage(), 0, $error);
            }
            return false;
        }
    }
}
