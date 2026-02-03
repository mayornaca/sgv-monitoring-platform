<?php

namespace App\Repository;

use App\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
class AppSettingRepository extends ServiceEntityRepository
{
    private const CACHE_KEY_PREFIX = 'app_setting_';
    private const CACHE_TTL = 3600; // 1 hora

    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Obtiene un setting por clave con caché
     */
    public function findByKey(string $key): ?AppSetting
    {
        // Intentar obtener desde caché
        if ($this->cache) {
            $cacheKey = self::CACHE_KEY_PREFIX . md5($key);
            $item = $this->cache->getItem($cacheKey);

            if ($item->isHit()) {
                return $item->get();
            }
        }

        // Buscar en BD
        $setting = $this->findOneBy(['key' => $key]);

        // Guardar en caché si existe
        if ($setting && $this->cache) {
            $item->set($setting);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        return $setting;
    }

    /**
     * Obtiene todos los settings de una categoría
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category], ['key' => 'ASC']);
    }

    /**
     * Obtiene solo los settings públicos
     */
    public function findPublicSettings(): array
    {
        return $this->findBy(['isPublic' => true], ['category' => 'ASC', 'key' => 'ASC']);
    }

    /**
     * Invalida el caché de un setting
     */
    public function invalidateCache(string $key): void
    {
        if ($this->cache) {
            $cacheKey = self::CACHE_KEY_PREFIX . md5($key);
            $this->cache->deleteItem($cacheKey);
        }
    }

    /**
     * Invalida todo el caché de settings
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->clear();
        }
    }

    /**
     * Guarda o actualiza un setting
     */
    public function saveSetting(AppSetting $setting): void
    {
        $em = $this->getEntityManager();
        $em->persist($setting);
        $em->flush();

        // Invalidar caché
        $this->invalidateCache($setting->getKey());
    }

    /**
     * Elimina un setting
     */
    public function deleteSetting(AppSetting $setting): void
    {
        $key = $setting->getKey();
        $em = $this->getEntityManager();
        $em->remove($setting);
        $em->flush();

        // Invalidar caché
        $this->invalidateCache($key);
    }

    /**
     * Verifica si existe una clave
     */
    public function keyExists(string $key): bool
    {
        return $this->count(['key' => $key]) > 0;
    }

    /**
     * Obtiene todas las claves de una categoría
     */
    public function getKeysByCategory(string $category): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.key')
            ->where('s.category = :category')
            ->setParameter('category', $category)
            ->orderBy('s.key', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_column($results, 'key');
    }
}
