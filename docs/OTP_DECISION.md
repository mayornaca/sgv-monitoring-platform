# Decisión de Implementación OTP

## Opción A: SchebTwoFactorBundle (Recomendado)
```bash
composer require scheb/2fa
```

**Pros:**
- ✅ Estándar de la industria
- ✅ Trusted devices automático
- ✅ Backup codes incluidos
- ✅ Rate limiting nativo
- ✅ Soporte API completo
- ✅ Mantenimiento por la comunidad

**Contras:**
- ❌ Configuración más compleja
- ❌ Learning curve del bundle

## Opción B: Implementación Custom Simple

**Pros:**
- ✅ Control total
- ✅ Aprovecha WhatsApp API existente
- ✅ Implementación rápida
- ✅ Menos dependencias

**Contras:**
- ❌ Reinventar características de seguridad
- ❌ Mantener código propio
- ❌ Sin trusted devices automático

## TODO(human): Decidir enfoque preferido

¿Prefieres la robustez del bundle estándar o una implementación simple custom?

## Implementación Híbrida (Recomendación)
Usar SchebTwoFactorBundle como base + custom WhatsApp provider:
- Core 2FA con el bundle
- Custom provider para WhatsApp Cloud API
- Email provider incluido en el bundle