<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ConfigurationService
{
    private const ENCRYPTION_KEY_ENV = 'APP_SECRET';

    public function __construct(
        private readonly AppSettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $appSecret
    ) {}

    /**
     * Obtiene un valor de configuración con tipado automático
     *
     * @param string $key Clave de configuración (ej: 'whatsapp.primary.token')
     * @param mixed $default Valor por defecto si no existe
     * @param bool $useEnvFallback Si debe intentar leer desde variables de entorno como fallback
     */
    public function get(string $key, mixed $default = null, bool $useEnvFallback = true): mixed
    {
        $setting = $this->settingRepository->findByKey($key);

        if (!$setting) {
            // Fallback a variables de entorno si está habilitado
            if ($useEnvFallback) {
                $envValue = $this->getFromEnv($key);
                if ($envValue !== null) {
                    return $envValue;
                }
            }

            return $default;
        }

        $value = $setting->getValue();

        // Desencriptar si es necesario
        if ($setting->getType() === AppSetting::TYPE_ENCRYPTED && $value !== null) {
            $value = $this->decrypt($value);
        }

        // Convertir al tipo correcto
        return $this->convertValue($value, $setting->getType());
    }

    /**
     * Establece un valor de configuración
     */
    public function set(string $key, mixed $value, ?string $type = null, ?string $category = null): void
    {
        $setting = $this->settingRepository->findByKey($key);

        if (!$setting) {
            $setting = new AppSetting();
            $setting->setKey($key);

            if ($type !== null) {
                $setting->setType($type);
            }

            if ($category !== null) {
                $setting->setCategory($category);
            }
        }

        // Encriptar si es necesario
        if ($setting->getType() === AppSetting::TYPE_ENCRYPTED && $value !== null) {
            $value = $this->encrypt((string) $value);
        }

        // Establecer el valor según el tipo
        if ($setting->getType() === AppSetting::TYPE_JSON) {
            $setting->setValue(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } elseif ($setting->getType() === AppSetting::TYPE_BOOLEAN) {
            $setting->setValue($value ? '1' : '0');
        } else {
            $setting->setValue($value !== null ? (string) $value : null);
        }

        $this->settingRepository->saveSetting($setting);
    }

    /**
     * Verifica si existe una clave
     */
    public function has(string $key): bool
    {
        return $this->settingRepository->keyExists($key);
    }

    /**
     * Elimina una configuración
     */
    public function delete(string $key): void
    {
        $setting = $this->settingRepository->findByKey($key);

        if ($setting) {
            $this->settingRepository->deleteSetting($setting);
        }
    }

    /**
     * Obtiene todas las configuraciones de una categoría
     */
    public function getByCategory(string $category, bool $includeEncrypted = false): array
    {
        $settings = $this->settingRepository->findByCategory($category);
        $result = [];

        foreach ($settings as $setting) {
            $value = $setting->getValue();

            // Desencriptar si es necesario y está permitido
            if ($setting->getType() === AppSetting::TYPE_ENCRYPTED) {
                if ($includeEncrypted && $value !== null) {
                    $value = $this->decrypt($value);
                } else {
                    $value = '***encrypted***';
                }
            }

            $result[$setting->getKey()] = $this->convertValue($value, $setting->getType());
        }

        return $result;
    }

    /**
     * Obtiene todas las configuraciones públicas (para API)
     */
    public function getPublicSettings(): array
    {
        $settings = $this->settingRepository->findPublicSettings();
        $result = [];

        foreach ($settings as $setting) {
            if ($setting->getType() !== AppSetting::TYPE_ENCRYPTED) {
                $result[$setting->getKey()] = $this->convertValue(
                    $setting->getValue(),
                    $setting->getType()
                );
            }
        }

        return $result;
    }

    /**
     * Invalida el caché de configuraciones
     */
    public function clearCache(?string $key = null): void
    {
        if ($key !== null) {
            $this->settingRepository->invalidateCache($key);
        } else {
            $this->settingRepository->clearCache();
        }
    }

    /**
     * Encripta un valor usando el APP_SECRET
     */
    private function encrypt(string $value): string
    {
        if (!extension_loaded('sodium')) {
            $this->logger->error('Sodium extension not available for encryption');
            throw new \RuntimeException('Sodium extension required for encryption');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = $this->deriveKey($this->appSecret);

        $encrypted = sodium_crypto_secretbox($value, $nonce, $key);

        return base64_encode($nonce . $encrypted);
    }

    /**
     * Desencripta un valor
     */
    private function decrypt(string $encrypted): ?string
    {
        if (!extension_loaded('sodium')) {
            $this->logger->error('Sodium extension not available for decryption');
            return null;
        }

        try {
            $decoded = base64_decode($encrypted);

            if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                $this->logger->error('Invalid encrypted value format');
                return null;
            }

            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $key = $this->deriveKey($this->appSecret);

            $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

            if ($decrypted === false) {
                $this->logger->error('Decryption failed - invalid key or corrupted data');
                return null;
            }

            return $decrypted;
        } catch (\Exception $e) {
            $this->logger->error('Decryption error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Deriva una clave de 32 bytes desde APP_SECRET
     */
    private function deriveKey(string $secret): string
    {
        return hash('sha256', $secret, true);
    }

    /**
     * Convierte un valor según su tipo
     */
    private function convertValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match($type) {
            AppSetting::TYPE_INTEGER => (int) $value,
            AppSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            AppSetting::TYPE_JSON => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Intenta obtener valor desde variables de entorno
     */
    private function getFromEnv(string $key): mixed
    {
        // Convertir app_setting.key.format a APP_SETTING_KEY_FORMAT
        $envKey = strtoupper(str_replace('.', '_', $key));

        $value = $_ENV[$envKey] ?? getenv($envKey);

        return $value !== false ? $value : null;
    }
}
