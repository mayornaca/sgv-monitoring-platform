# Re-validación de Números WhatsApp Business

## Problema

Cuando un número de WhatsApp Business tiene estado `code_verification_status: EXPIRED`, Meta acepta los mensajes pero NO los entrega. Esto causa que los mensajes aparezcan como "delivered" en la base de datos pero nunca lleguen al destinatario.

## Solución Simple: Comando CLI

Hemos creado un comando que permite re-validar números de forma simple desde la terminal.

### Paso 1: Obtener el código PIN

1. Abre [WhatsApp Business Manager](https://business.facebook.com)
2. Ve a **Phone Numbers** en el menú izquierdo
3. Selecciona el número que necesitas re-validar
4. Haz clic en **"Request code"** o **"Verify"**
5. Recibirás un código PIN de 6 dígitos en tu WhatsApp Business

### Paso 2: Ejecutar el comando

```bash
# Re-validar número PRINCIPAL (651420641396348 - Alertas SGV)
php bin/console app:whatsapp:revalidate 123456

# Re-validar número BACKUP (888885257633217 - Craetion Cloud)
php bin/console app:whatsapp:revalidate --phone=backup 123456
```

Reemplaza `123456` con el PIN real que recibiste.

### Paso 3: Verificar el resultado

El comando mostrará:
- Estado actual del número (EXPIRED, VERIFIED, etc.)
- Resultado de la re-validación
- Mensaje de éxito o error

## Estado Actual de los Números

### Número Principal (651420641396348)
- **Cuenta:** Alertas SGV
- **Estado:** EXPIRED (necesita re-validación)
- **Configurado en:** `WHATSAPP_PRIMARY_DSN`

### Número Backup (888885257633217)
- **Cuenta:** Craetion Cloud Spa
- **Estado:** VERIFIED (funcionando correctamente)
- **Configurado en:** `WHATSAPP_BACKUP_DSN`

## Failover Automático

El sistema tiene configurado failover automático:
- **Primer intento:** Usa número PRIMARY
- **Después de 3 reintentos fallidos:** Cambia automáticamente a número BACKUP
- **Tracking:** El campo `phone_number_used` en la tabla `whatsapp_messages` registra qué número envió cada mensaje

## Para Producción en Otro Dominio

Cuando migres el sistema a producción:

1. Copia el archivo `.env` al servidor de producción
2. Ajusta las credenciales si es necesario
3. Ejecuta las migraciones: `php bin/console doctrine:migrations:migrate`
4. Si algún número está expirado, usa el comando de re-validación:
   ```bash
   php bin/console app:whatsapp:revalidate <PIN>
   ```

## Verificar Estado de un Número

Puedes verificar el estado sin re-validar ejecutando el comando con un PIN inválido (mostrará el estado actual antes de intentar la re-validación).

O usando la API de Meta directamente:

```bash
curl "https://graph.facebook.com/v22.0/651420641396348?fields=display_phone_number,code_verification_status,quality_rating&access_token=TU_TOKEN"
```

## Troubleshooting

### Error: "PIN inválido"
- Verifica que el PIN sea de 6 dígitos
- Verifica que el PIN no haya expirado (solicita uno nuevo)
- Asegúrate de estar usando el PIN correcto para el número correcto

### Error: "Access token inválido"
- Verifica que el token en `.env` sea un System User token con permisos correctos
- El token debe tener permisos `whatsapp_business_management` y `whatsapp_business_messaging`

### Mensajes no llegan pero aparecen como "delivered"
- Este es el síntoma clásico de número EXPIRED
- Re-valida el número usando este comando
- Verifica que `code_verification_status` sea `VERIFIED` después de la re-validación

## Referencias

- [Meta WhatsApp Business API - Phone Numbers](https://developers.facebook.com/docs/whatsapp/cloud-api/phone-numbers)
- [Meta WhatsApp Business API - Register Phone](https://developers.facebook.com/docs/whatsapp/cloud-api/reference/phone-numbers#register-phone)
