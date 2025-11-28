# Permisos de Archivos Importantes

## Archivos .env

Los archivos de configuración `.env` deben tener permisos específicos para funcionar correctamente:

```bash
# Configuración correcta
-rw-r----- 1 opc www .env
-rw-r----- 1 opc www .env.prod

# Permisos: 640
# Owner: opc (puede leer y escribir)
# Group: www (puede leer para PHP-FPM)
# Others: sin acceso
```

### Problema Común

Si el sitio muestra **error 500** con mensaje "Unable to read the .env environment file", verifica los permisos:

```bash
ls -la /www/wwwroot/vs.gvops.cl/.env*
```

### Solución

```bash
# Corregir permisos
sudo chown opc:www /www/wwwroot/vs.gvops.cl/.env /www/wwwroot/vs.gvops.cl/.env.prod
sudo chmod 640 /www/wwwroot/vs.gvops.cl/.env /www/wwwroot/vs.gvops.cl/.env.prod
```

### ¿Por qué estos permisos?

- **PHP-FPM** corre como usuario `www` y necesita **leer** los archivos .env
- El usuario **opc** necesita **editar** los archivos .env
- Usar `opc:www` como owner:group permite ambos casos
- Permisos `640` = owner puede escribir, group puede leer, others sin acceso

### Archivos Afectados

- `.env` - Configuración principal
- `.env.prod` - Configuración de producción
- `.env.local` - Override local (si existe)

### Nota Importante

Cuando edites archivos `.env` directamente (con nano, vim, etc.), el sistema puede cambiar el owner a `opc:opc`. Después de editar, ejecuta:

```bash
sudo chown opc:www .env .env.prod
```
