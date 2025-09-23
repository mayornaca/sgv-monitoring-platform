# Guía de Deployment - Sistema de Monitoreo

## Configuración SMTP para Producción

### 1. Configurar variables de entorno

Antes del deployment, debe configurar la variable `MAILER_DSN` en el archivo `.env.prod`:

```bash
# Reemplazar con los datos reales del proveedor SMTP
MAILER_DSN=smtp://usuario:contraseña@smtp.gvops.cl:587
```

### 2. Ejemplos de configuración SMTP comunes

#### Gmail/Google Workspace
```bash
MAILER_DSN=gmail+smtp://username:password@default
# O con app password:
MAILER_DSN=smtp://username:app_password@smtp.gmail.com:587
```

#### SendGrid
```bash
MAILER_DSN=sendgrid+smtp://apikey:SG.xxxxx@default
```

#### Mailgun
```bash
MAILER_DSN=mailgun+smtp://username:password@default?region=us
```

#### SMTP Genérico
```bash
MAILER_DSN=smtp://usuario:contraseña@smtp.servidor.com:587
# Con TLS/SSL:
MAILER_DSN=smtp://usuario:contraseña@smtp.servidor.com:465?encryption=ssl
```

### 3. Configuración del servidor

Asegúrese de que el servidor tenga conectividad saliente en los puertos:
- **587** (STARTTLS)
- **465** (SSL/TLS)

### 4. Testing de configuración

Para probar la configuración SMTP:

```bash
# Limpiar cache
php bin/console cache:clear --env=prod

# Enviar email de prueba (crear comando de testing)
php bin/console app:test-email admin@gvops.cl
```

### 5. Logs de errores

Los errores de SMTP se registran en:
- `var/log/prod.log` (producción)
- `var/log/dev.log` (desarrollo)

Buscar por: `Failed to send OTP email`

### 6. Configuración adicional

#### Headers personalizados
Los emails se envían desde: `Sistema de Monitoreo <no-reply@gvops.cl>`
Reply-to: `soporte@gvops.cl`

#### Configuración de envelope
Sender: `no-reply@gvops.cl`

## Checklist de Deployment

- [ ] Configurar `MAILER_DSN` en `.env.prod`
- [ ] Verificar conectividad de red (puertos 587/465)
- [ ] Probar envío de email
- [ ] Verificar logs de aplicación
- [ ] Configurar monitoreo de delivery de emails
- [ ] Configurar backup codes como fallback

## Troubleshooting

### Error: "No se pudo enviar el email de verificación"
1. Verificar credenciales SMTP
2. Verificar conectividad de red
3. Revisar logs del servidor SMTP
4. Verificar que el dominio no esté en blacklist

### Error: "Authentication failed"
1. Verificar usuario/contraseña
2. Para Gmail: usar App Password
3. Verificar 2FA en cuenta de email

### Email no llega
1. Verificar spam/junk folder
2. Verificar reputación del servidor IP
3. Configurar SPF/DKIM records en DNS