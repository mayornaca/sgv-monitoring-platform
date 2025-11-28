#!/bin/bash
# Script para habilitar embedding de Grafana en iframe
# Proyecto: vs.gvops.cl
# Fecha: 2025-11-06

set -e

GRAFANA_DIR="/www/dk_project/dk_app/grafana/grafana_xFTk"
DOCS_DIR="/www/wwwroot/vs.gvops.cl/docs"

echo "=================================================="
echo "Habilitar Grafana Embedding en iframe"
echo "=================================================="

# Verificar que existe el directorio de Grafana
if [ ! -d "$GRAFANA_DIR" ]; then
    echo "❌ Error: No se encontró el directorio de Grafana en $GRAFANA_DIR"
    exit 1
fi

# Copiar archivo de configuración
echo "📝 Copiando grafana.ini..."
sudo cp "$DOCS_DIR/grafana.ini" "$GRAFANA_DIR/grafana.ini"
sudo chmod 644 "$GRAFANA_DIR/grafana.ini"

# Backup del docker-compose.yml actual
echo "💾 Creando backup de docker-compose.yml..."
sudo cp "$GRAFANA_DIR/docker-compose.yml" "$GRAFANA_DIR/docker-compose.yml.backup.$(date +%Y%m%d%H%M%S)"

# Verificar si ya existe el volumen en docker-compose.yml
if grep -q "grafana.ini:/etc/grafana/grafana.ini" "$GRAFANA_DIR/docker-compose.yml"; then
    echo "✅ El volumen grafana.ini ya está configurado en docker-compose.yml"
else
    echo "⚠️  ATENCIÓN: Necesitas agregar manualmente el volumen al docker-compose.yml"
    echo ""
    echo "Edita el archivo: $GRAFANA_DIR/docker-compose.yml"
    echo ""
    echo "Agrega esta línea en la sección 'volumes':"
    echo "      - \${APP_PATH}/grafana.ini:/etc/grafana/grafana.ini"
    echo ""
    echo "Ejemplo completo:"
    echo "    volumes:"
    echo "      - \${APP_PATH}/data:/var/lib/grafana"
    echo "      - \${APP_PATH}/grafana.ini:/etc/grafana/grafana.ini  # ← AGREGAR ESTA LÍNEA"
    echo ""
    read -p "Presiona Enter cuando hayas editado el archivo..."
fi

# Reiniciar contenedor de Grafana
echo "🔄 Reiniciando contenedor de Grafana..."
cd "$GRAFANA_DIR"
sudo docker-compose down
sudo docker-compose up -d

# Esperar que arranque
echo "⏳ Esperando que Grafana inicie..."
sleep 5

# Verificar configuración
echo "🔍 Verificando configuración..."
ALLOW_EMBEDDING=$(sudo docker exec grafana_xftk-grafana_xFTk-1 cat /etc/grafana/grafana.ini 2>/dev/null | grep "allow_embedding" | grep -v "^;")

if [ ! -z "$ALLOW_EMBEDDING" ]; then
    echo "✅ Configuración aplicada correctamente:"
    echo "   $ALLOW_EMBEDDING"
else
    echo "❌ Error: No se pudo verificar la configuración"
    exit 1
fi

# Verificar que Grafana responde
echo "🌐 Verificando que Grafana responde..."
if curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000 | grep -q "302\|200"; then
    echo "✅ Grafana está respondiendo correctamente"
else
    echo "⚠️  Grafana no responde aún, puede tardar unos segundos más"
fi

echo ""
echo "=================================================="
echo "✅ Proceso completado"
echo "=================================================="
echo ""
echo "Ahora puedes acceder al visor de Grafana en:"
echo "https://vs.gvops.cl/admin/grafana"
echo ""
echo "Si aún ves el error X-Frame-Options, limpia el caché del navegador."
echo ""
