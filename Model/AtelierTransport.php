<?php
declare(strict_types=1);

namespace Atelier\EmailSender\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Mail\Transport;
use Atelier\EmailSender\Model\LogFactory;
use Magento\Framework\Mail\Address;
use Magento\Framework\Encryption\EncryptorInterface;

class AtelierTransport extends Transport
{
    private ScopeConfigInterface $scopeConfig;
    private LogFactory $logFactory;
    private EmailMessageInterface $message;
    private EncryptorInterface $encryptor;

    public function __construct(
        EmailMessageInterface $message,
        ScopeConfigInterface $scopeConfig,
        LogFactory $logFactory,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($message);
        $this->message = $message;
        $this->scopeConfig = $scopeConfig;
        $this->logFactory = $logFactory;
        $this->encryptor = $encryptor;
    }

    public function sendMessage(): void
    {
        $message = $this->message;
        $encryptedApiKey = (string) $this->scopeConfig->getValue('atelier_email/general/api_key', ScopeInterface::SCOPE_STORE);
        $apiKey = $this->encryptor->decrypt($encryptedApiKey);
        $enableTestMode = (bool) $this->scopeConfig->isSetFlag('atelier_email/general/test_mode', ScopeInterface::SCOPE_STORE);
        $testEmail = (string) $this->scopeConfig->getValue('atelier_email/general/test_email', ScopeInterface::SCOPE_STORE);

        try {
            // Validación del destinatario
            $to = $enableTestMode ? $testEmail : $this->getEmailTo($message);
            $originalTo = $this->getEmailTo($message);
            if (!$to) {
                throw new MailException(__('No recipient email address found.'));
            }

            $subject = $message->getSubject() ?? 'No Subject';
            $body = $this->extractBodyContent($message);
            $body = quoted_printable_decode($body);

            // Añadir destinatario original al principio del cuerpo del correo
            if ($enableTestMode && $originalTo !== $testEmail) {
                $destinatarioInfo = '<div style="background-color:#f8f9fa;padding:10px;margin-bottom:15px;border-left:4px solid #007bff;">
                    <strong>Destinatario original:</strong> ' . htmlspecialchars($originalTo) . 
                '</div>';
                
                // Insertar después del tag body
                if (stripos($body, '<body') !== false) {
                    $body = preg_replace('/<body[^>]*>/', '$0' . $destinatarioInfo, $body);
                } else {
                    $body = str_replace('<html>', '<html><body>' . $destinatarioInfo, $body);
                    if (stripos($body, '<body') === false) {
                        $body = str_replace('</html>', '</body></html>', $body);
                    }
                }
            }
            
            // Obtenemos la información del remitente original para usarlo como Reply-To
            $originalSenderName = $this->getSenderName($message);
            $originalSenderEmail = $this->getSenderEmail($message);
            
            // Usamos una dirección de Brevo como remitente
            // para evitar el error de dominio no habilitado
            $brevoSenderEmail = 'noreply@brevo.com';
            
            // Formato compatible con la API de Brevo
            $payload = [
                'sender' => [
                    'name' => $originalSenderName, // Mantenemos el nombre original
                    'email' => $brevoSenderEmail   // Usamos email de Brevo
                ],
                'to' => [['email' => $to, 'name' => $to]],
                'subject' => $subject,
                'htmlContent' => $body,
                'replyTo' => [
                    'email' => $originalSenderEmail,
                    'name' => $originalSenderName
                ]
            ];

            $response = $this->sendRequest('https://api.brevo.com/v3/smtp/email', $payload, $apiKey);

            // Guardar log del email enviado
            $log = $this->logFactory->create();
            $log->setData([
                'email_to' => $to,
                'email_subject' => $subject,
                'email_body' => $body,
                'status' => isset($response['messageId']) ? 'SENT' : ($response['error'] ?? 'ERROR')
            ]);
            $log->save();

            if (isset($response['error'])) {
                throw new MailException(__('Brevo API Error: %1', $response['error']));
            }
        } catch (\Exception $e) {
            throw new MailException(__('Error sending email: %1', $e->getMessage()));
        }
    }

    /**
     * Obtiene el email del destinatario.
     */
    private function getEmailTo(EmailMessageInterface $message): string
    {
        $addresses = $message->getTo();
        if (!empty($addresses) && $addresses[0] instanceof Address) {
            return $addresses[0]->getEmail();
        }
        return '';
    }

    /**
     * Obtiene el nombre del remitente.
     */
    private function getSenderName(EmailMessageInterface $message): string
    {
        $addresses = $message->getFrom();
        if (!empty($addresses) && $addresses[0] instanceof Address) {
            return $addresses[0]->getName() ?: $addresses[0]->getEmail();
        }
        return 'Remitente desconocido';
    }

    /**
     * Obtiene el email del remitente.
     */
    private function getSenderEmail(EmailMessageInterface $message): string
    {
        $addresses = $message->getFrom();
        if (!empty($addresses) && $addresses[0] instanceof Address) {
            return $addresses[0]->getEmail();
        }
        return 'no-reply@example.com';
    }

    /**
     * Extrae el contenido del cuerpo del email.
     *
     * @param EmailMessageInterface $message
     * @return string
     */
    private function extractBodyContent(EmailMessageInterface $message): string
    {
        try {
            // Intentamos con getBody() que es el método estándar
            $body = $message->getBody();
            
            // Si el cuerpo es una cadena, la procesamos
            if (is_string($body)) {
                // Decodificamos si es necesario
                if (strpos($body, '=0A') !== false || strpos($body, '=0D') !== false) {
                    $body = quoted_printable_decode($body);
                }
                
                // Aseguramos que tiene estructura HTML
                if (stripos($body, '<html') === false) {
                    $body = '<html><body>' . nl2br(htmlspecialchars($body)) . '</body></html>';
                }
                return $body;
            }
            
            // Para Magento 2.4.x que usa Laminas\Mime\Message
            if ($body instanceof \Laminas\Mime\Message) {
                $parts = $body->getParts();
                
                // Primero buscamos partes HTML
                foreach ($parts as $part) {
                    if ($part instanceof \Laminas\Mime\Part && stripos($part->type, 'text/html') !== false) {
                        // Aseguramos que el contenido esté correctamente decodificado
                        $content = $part->getContent();
                        if ($part->encoding === 'quoted-printable') {
                            $content = quoted_printable_decode($content);
                        } elseif ($part->encoding === 'base64') {
                            $content = base64_decode($content);
                        }
                        
                        if (!empty($content)) {
                            // Aseguramos que tiene estructura HTML completa
                            if (stripos($content, '<html') === false) {
                                $content = '<html><body>' . $content . '</body></html>';
                            }
                            return $content;
                        }
                    }
                }
                
                // Si no encontramos HTML, buscamos texto plano
                foreach ($parts as $part) {
                    if ($part instanceof \Laminas\Mime\Part && stripos($part->type, 'text/plain') !== false) {
                        $content = $part->getContent();
                        if ($part->encoding === 'quoted-printable') {
                            $content = quoted_printable_decode($content);
                        } elseif ($part->encoding === 'base64') {
                            $content = base64_decode($content);
                        }
                        
                        if (!empty($content)) {
                            // Convertimos el texto plano a HTML
                            return '<html><body>' . nl2br(htmlspecialchars($content)) . '</body></html>';
                        }
                    }
                }
                
                // Última opción: usar cualquier parte con contenido
                foreach ($parts as $part) {
                    if ($part instanceof \Laminas\Mime\Part && !empty($part->getContent())) {
                        $content = $part->getContent();
                        if ($part->encoding === 'quoted-printable') {
                            $content = quoted_printable_decode($content);
                        } elseif ($part->encoding === 'base64') {
                            $content = base64_decode($content);
                        }
                        
                        // Verificamos si parece ser HTML
                        if (stripos($content, '<html') !== false || stripos($content, '<body') !== false) {
                            return $content;
                        } else {
                            // Convertimos a HTML básico si no parece ser HTML
                            return '<html><body>' . nl2br(htmlspecialchars($content)) . '</body></html>';
                        }
                    }
                }
            }
            
            // Último recurso: extraer contenido del mensaje raw
            if (method_exists($message, 'getRawMessage')) {
                $rawMessage = $message->getRawMessage();
                
                // Intentamos encontrar contenido HTML
                if (preg_match('/<html[^>]*>(.*?)<\/html>/is', $rawMessage, $matches)) {
                    return '<html>' . $matches[1] . '</html>';
                }
                
                // Intentamos encontrar al menos un cuerpo HTML
                if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $rawMessage, $matches)) {
                    return '<html><body>' . $matches[1] . '</body></html>';
                }
                
                // Si hay contenido quoted-printable, lo decodificamos
                if (strpos($rawMessage, '=0A') !== false || strpos($rawMessage, '=0D') !== false) {
                    $decodedRaw = quoted_printable_decode($rawMessage);
                    // Aseguramos que tiene estructura HTML
                    return '<html><body>' . nl2br(htmlspecialchars($decodedRaw)) . '</body></html>';
                }
                
                // Si el contenido raw parece ser plano, lo convertimos a HTML
                return '<html><body>' . nl2br(htmlspecialchars($rawMessage)) . '</body></html>';
            }
            
            // Si todo falla, devolvemos un mensaje de error en HTML
            return '<html><body><p>No se pudo extraer el contenido del email correctamente.</p></body></html>';
        } catch (\Exception $e) {
            return '<html><body><p>Error al extraer el contenido: ' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
        }
    }

    /**
     * Envía una solicitud HTTP con cURL a la API de Brevo.
     *
     * @param string $url
     * @param array $payload
     * @param string $apiKey
     * @return array
     */
    private function sendRequest(string $url, array $payload, string $apiKey): array
    {
        $curl = curl_init();
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

        if (!$payloadJson) {
            throw new MailException(__('Error encoding JSON payload.'));
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "api-key: " . $apiKey,
                "content-type: application/json"
            ],
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return ['error' => $error];
        }

        if (!$response) {
            return ['error' => 'Empty response from API'];
        }

        try {
            $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            return $responseData;
        } catch (\JsonException $e) {
            throw new MailException(__('JSON decode error: %1', $e->getMessage()));
        }
    }
}