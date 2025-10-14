#!/usr/bin/env node

/**
 * Script pour générer les icônes PNG à partir des SVG
 *
 * Installation : npm install sharp
 * Usage : node generate-icons.js
 */

const fs = require('fs');
const path = require('path');

async function generateIcons() {
  // Vérifier si sharp est installé
  let sharp;
  try {
    sharp = require('sharp');
  } catch (error) {
    console.error('❌ Le package "sharp" n\'est pas installé.');
    console.error('📦 Installez-le avec : npm install sharp');
    console.error('');
    console.error('Alternative : Utilisez un service en ligne comme :');
    console.error('  - https://cloudconvert.com/svg-to-png');
    console.error('  - https://svgtopng.com/');
    console.log('');
    console.log('Fichiers à convertir :');
    console.log('  - assets/icons/icon-192.svg → icon-192.png');
    console.log('  - assets/icons/icon-512.svg → icon-512.png');
    console.log('  - assets/icons/icon-192-maskable.svg → icon-192-maskable.png');
    console.log('  - assets/icons/icon-512-maskable.svg → icon-512-maskable.png');
    console.log('  - assets/icons/apple-touch-icon.svg → apple-touch-icon.png (180x180)');
    process.exit(1);
  }

  const iconsDir = path.join(__dirname, 'assets', 'icons');

  const conversions = [
    { input: 'icon-192.svg', output: 'icon-192.png', size: 192 },
    { input: 'icon-512.svg', output: 'icon-512.png', size: 512 },
    { input: 'icon-192-maskable.svg', output: 'icon-192-maskable.png', size: 192 },
    { input: 'icon-512-maskable.svg', output: 'icon-512-maskable.png', size: 512 },
    { input: 'apple-touch-icon.svg', output: 'apple-touch-icon.png', size: 180 },
  ];

  console.log('🎨 Génération des icônes PNG...\n');

  for (const { input, output, size } of conversions) {
    try {
      const inputPath = path.join(iconsDir, input);
      const outputPath = path.join(iconsDir, output);

      await sharp(inputPath)
        .resize(size, size)
        .png()
        .toFile(outputPath);

      console.log(`✅ ${output} (${size}x${size})`);
    } catch (error) {
      console.error(`❌ Erreur lors de la conversion de ${input}:`, error.message);
    }
  }

  console.log('\n✨ Génération terminée !');
}

generateIcons().catch(console.error);
