#!/bin/bash

# Script de build pour optimiser les assets et gérer le cache-busting
# Usage: ./scripts/build-assets.sh

set -e

echo "🚀 Build des assets avec cache-busting..."

# Créer le répertoire de build s'il n'existe pas
BUILD_DIR="build"
mkdir -p "$BUILD_DIR"

# Copier les fichiers statiques
echo "📁 Copie des fichiers statiques..."
cp -r frontend/* "$BUILD_DIR/"

# Créer un fichier de manifest pour le cache-busting
MANIFEST_FILE="$BUILD_DIR/assets-manifest.json"
echo "📝 Génération du manifest..."

cat > "$MANIFEST_FILE" << EOF
{
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "version": "$(git rev-parse --short HEAD 2>/dev/null || echo 'dev')",
  "assets": {
EOF

# Traiter les fichiers CSS
echo "🎨 Traitement des CSS..."
if [ -f "$BUILD_DIR/assets/css/app.css" ]; then
    CSS_HASH=$(md5sum "$BUILD_DIR/assets/css/app.css" | cut -d' ' -f1)
    echo "    \"app.css\": \"app.${CSS_HASH}.css\"," >> "$MANIFEST_FILE"
    mv "$BUILD_DIR/assets/css/app.css" "$BUILD_DIR/assets/css/app.${CSS_HASH}.css"
fi

# Traiter les fichiers JS
echo "⚡ Traitement des JS..."
if [ -f "$BUILD_DIR/assets/js/app.js" ]; then
    JS_HASH=$(md5sum "$BUILD_DIR/assets/js/app.js" | cut -d' ' -f1)
    echo "    \"app.js\": \"app.${JS_HASH}.js\"," >> "$MANIFEST_FILE"
    mv "$BUILD_DIR/assets/js/app.js" "$BUILD_DIR/assets/js/app.${JS_HASH}.js"
fi

# Traiter les images (si elles existent)
echo "🖼️  Traitement des images..."
if [ -d "$BUILD_DIR/assets/images" ]; then
    for img in "$BUILD_DIR/assets/images"/*.png "$BUILD_DIR/assets/images"/*.jpg "$BUILD_DIR/assets/images"/*.svg; do
        if [ -f "$img" ]; then
            filename=$(basename "$img")
            name="${filename%.*}"
            ext="${filename##*.}"
            hash=$(md5sum "$img" | cut -d' ' -f1)
            newname="${name}.${hash}.${ext}"
            echo "    \"$filename\": \"$newname\"," >> "$MANIFEST_FILE"
            mv "$img" "$BUILD_DIR/assets/images/$newname"
        fi
    done
fi

# Fermer le JSON du manifest (supprimer la dernière virgule)
sed -i '$ s/,$//' "$MANIFEST_FILE"
echo "  }" >> "$MANIFEST_FILE"
echo "}" >> "$MANIFEST_FILE"

# Générer un fichier PHP pour servir les assets avec cache-busting
echo "🐘 Génération du helper PHP..."
cat > "$BUILD_DIR/assets.php" << 'EOF'
<?php
/**
 * Helper pour servir les assets avec cache-busting
 */

function asset($path) {
    static $manifest = null;
    
    if ($manifest === null) {
        $manifestPath = __DIR__ . '/assets-manifest.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
        } else {
            $manifest = [];
        }
    }
    
    // Extraire le nom du fichier
    $filename = basename($path);
    
    // Chercher dans le manifest
    if (isset($manifest['assets'][$filename])) {
        $hashedFilename = $manifest['assets'][$filename];
        $dir = dirname($path);
        return ($dir !== '.' ? $dir . '/' : '') . $hashedFilename;
    }
    
    // Fallback vers le fichier original
    return $path;
}

// Fonction pour obtenir la version (pour cache-busting global)
function appVersion() {
    static $version = null;
    
    if ($version === null) {
        $manifestPath = __DIR__ . '/assets-manifest.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $version = $manifest['version'] ?? 'dev';
        } else {
            $version = 'dev';
        }
    }
    
    return $version;
}
?>
EOF

echo "✅ Build terminé !"
echo "📊 Statistiques:"
echo "   - Répertoire de build: $BUILD_DIR"
echo "   - Manifest: $MANIFEST_FILE"
echo "   - Helper PHP: $BUILD_DIR/assets.php"
echo ""
echo "🔧 Pour utiliser dans votre HTML:"
echo "   <?php require 'assets.php'; ?>"
echo "   <link href=\"<?= asset('assets/css/app.css') ?>\" rel=\"stylesheet\">"
echo "   <script src=\"<?= asset('assets/js/app.js') ?>\"></script>"
