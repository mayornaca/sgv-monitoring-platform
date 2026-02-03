<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Servicio para validar Google reCAPTCHA v2
 *
 * Integración manual sin bundles externos para mayor control y compatibilidad
 * con el sistema AJAX existente del login.
 */
class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private string $siteKey;
    private string $secretKey;

    public function __construct(
        ?string $siteKey,
        ?string $secretKey,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
        $this->siteKey = $siteKey ?? '';
        $this->secretKey = $secretKey ?? '';
    }

    /**
     * Obtiene la site key para el frontend
     */
    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    /**
     * Verifica si reCAPTCHA está configurado
     */
    public function isEnabled(): bool
    {
        return !empty($this->siteKey) && !empty($this->secretKey);
    }

    /**
     * Valida el token de respuesta de reCAPTCHA
     *
     * @param string|null $recaptchaResponse Token del widget reCAPTCHA
     * @param string|null $remoteIp IP del cliente (opcional, mejora seguridad)
     * @return bool True si validación exitosa o reCAPTCHA no configurado
     */
    public function validate(?string $recaptchaResponse, ?string $remoteIp = null): bool
    {
        // Skip validation if not configured (desarrollo/testing)
        if (!$this->isEnabled()) {
            $this->logger->debug('reCAPTCHA: Skipped validation (not configured)');
            return true;
        }

        if (empty($recaptchaResponse)) {
            $this->logger->warning('reCAPTCHA: Empty response token');
            return false;
        }

        try {
            $params = [
                'secret' => $this->secretKey,
                'response' => $recaptchaResponse,
            ];

            if ($remoteIp) {
                $params['remoteip'] = $remoteIp;
            }

            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => $params,
            ]);

            $data = $response->toArray();

            if (!($data['success'] ?? false)) {
                $this->logger->warning('reCAPTCHA validation failed', [
                    'error-codes' => $data['error-codes'] ?? [],
                ]);
                return false;
            }

            $this->logger->debug('reCAPTCHA validation successful');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('reCAPTCHA API error', [
                'exception' => $e->getMessage(),
            ]);
            // En caso de error de API, permitir acceso para no bloquear usuarios
            // Considerar cambiar a false en producción de alta seguridad
            return false;
        }
    }
}
