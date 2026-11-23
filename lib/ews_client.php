<?php

/** HTTPS-only Exchange requests. Passwords and raw responses must never be logged. */
final class EwsClient
{
    private const M = 'http://schemas.microsoft.com/exchange/services/2006/messages';
    private const T = 'http://schemas.microsoft.com/exchange/services/2006/types';
    private const S = 'http://schemas.xmlsoap.org/soap/envelope/';

    public function __construct(
        private string $url,
        private string $username,
        private string $password,
        private string $mailbox
    ) {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('EWS requires a valid HTTPS endpoint.');
        }
        if ($username === '' || $password === '' || !filter_var($mailbox, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $username)) {
            throw new RuntimeException('EWS credentials are incomplete.');
        }
        if (!extension_loaded('curl') || !class_exists(DOMDocument::class)) {
            throw new RuntimeException('EWS requires PHP curl and DOM extensions.');
        }
        if (!(curl_version()['features'] & CURL_VERSION_NTLM)) {
            throw new RuntimeException('This PHP curl build does not support NTLM authentication.');
        }
    }

    /** Read only: returns the identity of the caller's inbox, without message contents. */
    public function checkMailboxAccess(): void
    {
        $this->request('GetFolder',
            '<m:GetFolder><m:FolderShape><t:BaseShape>IdOnly</t:BaseShape></m:FolderShape>' .
            '<m:FolderIds><t:DistinguishedFolderId Id="inbox" /></m:FolderIds></m:GetFolder>'
        );
    }

    /** Send a MIME message, retaining UTF-8 bodies and file attachments. No automatic retries. */
    public function sendMime(string $mime, array $to, array $cc = [], array $bcc = []): void
    {
        if ($mime === '' || count($to) + count($cc) + count($bcc) === 0) {
            throw new RuntimeException('EWS message or recipients are empty.');
        }
        $body = '<m:CreateItem MessageDisposition="SendAndSaveCopy">' .
            '<m:SavedItemFolderId><t:DistinguishedFolderId Id="sentitems" /></m:SavedItemFolderId>' .
            '<m:Items><t:Message><t:MimeContent CharacterSet="UTF-8">' . base64_encode($mime) .
            '</t:MimeContent>';
        foreach (['ToRecipients' => $to, 'CcRecipients' => $cc, 'BccRecipients' => $bcc] as $field => $recipients) {
            if (!$recipients) {
                continue;
            }
            $body .= '<t:' . $field . '>';
            foreach ($recipients as $recipient) {
                $email = (string)($recipient[0] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('EWS recipient address is invalid.');
                }
                $body .= '<t:Mailbox><t:EmailAddress>' .
                    htmlspecialchars($email, ENT_XML1 | ENT_QUOTES, 'UTF-8') .
                    '</t:EmailAddress></t:Mailbox>';
            }
            $body .= '</t:' . $field . '>';
        }
        $this->request('CreateItem', $body . '</t:Message></m:Items></m:CreateItem>');
    }

    private function request(string $operation, string $body): DOMDocument
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<s:Envelope xmlns:s="' . self::S . '" xmlns:m="' . self::M .
            '" xmlns:t="' . self::T . '"><s:Header>' .
            '<t:RequestServerVersion Version="Exchange2013" />' .
            '</s:Header><s:Body>' . $body . '</s:Body></s:Envelope>';

        $handle = curl_init($this->url);
        if ($handle === false) {
            throw new RuntimeException('EWS connection could not be initialized.');
        }
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $xml,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml; charset=utf-8',
                    'SOAPAction: "' . self::M . '/' . $operation . '"',
                    'X-AnchorMailbox: ' . $this->mailbox,
                    'Expect:',
                ],
                CURLOPT_HTTPAUTH => CURLAUTH_NTLM,
                CURLOPT_USERNAME => $this->username,
                CURLOPT_PASSWORD => $this->password,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'BPMN-Portal-EWS/1.0',
            ]);
            $response = curl_exec($handle);
            $error = curl_errno($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
        if ($response === false) {
            throw new RuntimeException('EWS HTTPS connection failed (curl ' . $error . ').');
        }
        if ($status === 401) {
            throw new RuntimeException('EWS rejected the login. Check the password and login format.');
        }
        if ($status === 403) {
            throw new RuntimeException('Exchange denied EWS access for this account.');
        }
        if ($status !== 200) {
            throw new RuntimeException('EWS returned HTTP ' . $status . '.');
        }
        return self::validateResponse($response, $operation);
    }

    public static function validateResponse(string $xml, string $operation): DOMDocument
    {
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new RuntimeException('EWS returned an unsupported XML document.');
        }
        $document = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        if (!$loaded) {
            throw new RuntimeException('EWS returned invalid XML.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', self::S);
        $xpath->registerNamespace('m', self::M);
        $messages = $xpath->query('/s:Envelope/s:Body/m:' . $operation .
            'Response/m:ResponseMessages/m:' . $operation . 'ResponseMessage');
        if ($messages === false || $messages->length !== 1) {
            throw new RuntimeException('EWS returned an unexpected response.');
        }
        $message = $messages->item(0);
        $code = $xpath->evaluate('string(m:ResponseCode)', $message);
        if ($message->getAttribute('ResponseClass') !== 'Success' || $code !== 'NoError') {
            $safeCode = preg_match('/^[A-Za-z0-9_]{1,100}$/', $code) ? $code : 'UnknownError';
            throw new RuntimeException('Exchange rejected the request: ' . $safeCode . '.');
        }
        return $document;
    }
}
