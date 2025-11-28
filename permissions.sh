#!/bin/bash

# Script para gestionar permisos del proyecto vs.gvops.cl
# Uso: ./permissions.sh [dev|prod]

PROJECT_DIR="/www/wwwroot/vs.gvops.cl"

case "$1" in
    dev)
        echo "Aplicando permisos de desarrollo (opc:www)..."

        # Owner/group para desarrollo
        sudo chown -R opc:www "$PROJECT_DIR"

        # Directorios: 775
        sudo find "$PROJECT_DIR" -type d -exec chmod 775 {} \;

        # Archivos: 664
        sudo find "$PROJECT_DIR" -type f -exec chmod 664 {} \;

        # Scripts ejecutables
        sudo chmod +x "$PROJECT_DIR/bin/"*
        sudo chmod +x "$PROJECT_DIR/permissions.sh"

        # .env editable por opc
        sudo chmod 660 "$PROJECT_DIR/.env" "$PROJECT_DIR/.env.prod" 2>/dev/null

        echo "Permisos de desarrollo aplicados."
        echo "  - opc puede editar todos los archivos"
        echo "  - www puede leer/escribir donde necesita"
        ;;

    prod)
        echo "Aplicando permisos de producción (www:www)..."

        # Owner/group para producción
        sudo chown -R www:www "$PROJECT_DIR"

        # Directorios: 755
        sudo find "$PROJECT_DIR" -type d -exec chmod 755 {} \;

        # Archivos: 644
        sudo find "$PROJECT_DIR" -type f -exec chmod 644 {} \;

        # Scripts ejecutables
        sudo chmod +x "$PROJECT_DIR/bin/"*
        sudo chmod +x "$PROJECT_DIR/permissions.sh"

        # .env restringido
        sudo chmod 640 "$PROJECT_DIR/.env" "$PROJECT_DIR/.env.prod" 2>/dev/null

        # Directorios que PHP necesita escribir
        sudo chmod 775 "$PROJECT_DIR/var"
        sudo chmod -R 775 "$PROJECT_DIR/var/cache"
        sudo chmod -R 775 "$PROJECT_DIR/var/log"
        sudo chmod 775 "$PROJECT_DIR/public/uploads" 2>/dev/null
        sudo chmod 775 "$PROJECT_DIR/public/downloads" 2>/dev/null

        echo "Permisos de producción aplicados."
        echo "  - www es owner de todos los archivos"
        echo "  - Permisos más restrictivos para seguridad"
        ;;

    *)
        echo "Uso: $0 [dev|prod]"
        echo ""
        echo "  dev   - Permisos para desarrollo (opc:www, 664/775)"
        echo "          Permite a opc editar archivos"
        echo ""
        echo "  prod  - Permisos para producción (www:www, 644/755)"
        echo "          Más restrictivo, solo www puede escribir"
        exit 1
        ;;
esac
