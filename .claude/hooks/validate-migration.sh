#!/bin/bash
# Hook de validación para migraciones de reportes
# Se ejecuta automáticamente después de modificar controladores SIV

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}🔍 Validando migración...${NC}"

# Detectar si se modificó SivController
if git diff --name-only | grep -q "SivController.php"; then
    echo -e "${YELLOW}⚠️  Detectado cambio en SivController${NC}"

    # Verificar templates base incorrectos
    WRONG_TEMPLATES=$(grep -r "base_modern.html.twig" templates/ 2>/dev/null | wc -l)
    if [ "$WRONG_TEMPLATES" -gt 0 ]; then
        echo -e "${RED}❌ ERROR: Encontrados templates con base_modern.html.twig${NC}"
        echo "   Use 'admin/layout.html.twig' en su lugar"
        exit 1
    fi

    # Verificar rutas duplicadas o mal nombradas
    ROUTES=$(grep -E "@Route|#\[.*Route" src/Controller/Dashboard/SivController.php | grep -oP "name:\s*['\"]([^'\"]+)" | cut -d'"' -f2 | cut -d"'" -f2)

    for route in $ROUTES; do
        # Verificar si la ruta está en el menú
        if ! grep -q "$route" src/Controller/Admin/DashboardController.php 2>/dev/null; then
            echo -e "${YELLOW}⚠️  Ruta '$route' no encontrada en el menú${NC}"
        fi
    done

    # Limpiar cache automáticamente
    echo "Limpiando cache..."
    php bin/console cache:clear >/dev/null 2>&1

    echo -e "${GREEN}✅ Validación completada${NC}"
fi

exit 0