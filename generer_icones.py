#!/usr/bin/env python3
"""
generer_icones.py — Génère toutes les icônes PWA pour WhatAPlant
Exécuter une seule fois : python3 generer_icones.py
Nécessite : pip install Pillow
"""
import os
from PIL import Image, ImageDraw, ImageFont

os.makedirs('icons', exist_ok=True)

TAILLES = [72, 96, 128, 192, 512]

def generer_icone(taille):
    img  = Image.new('RGBA', (taille, taille), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Fond circulaire vert
    marge  = int(taille * 0.04)
    draw.ellipse([marge, marge, taille-marge, taille-marge],
                 fill=(13, 92, 58, 255))

    # Cercle intérieur plus clair
    m2 = int(taille * 0.12)
    draw.ellipse([m2, m2, taille-m2, taille-m2],
                 fill=(27, 138, 94, 255))

    # Emoji 🌿 en texte (approximation avec cercles)
    cx, cy = taille // 2, taille // 2
    r = int(taille * 0.28)

    # Tige
    draw.line([(cx, cy + r//2), (cx, cy - r//3)],
              fill=(255, 255, 255, 220), width=max(2, taille//40))

    # Feuille gauche
    draw.ellipse([cx - r, cy - r//2, cx, cy + r//4],
                 fill=(52, 211, 153, 230))
    # Feuille droite
    draw.ellipse([cx, cy - r//2, cx + r, cy + r//4],
                 fill=(167, 243, 208, 200))
    # Feuille centrale
    draw.ellipse([cx - r//2, cy - r, cx + r//2, cy],
                 fill=(52, 211, 153, 210))

    # Lettre W au bas
    font_size = max(12, taille // 8)
    try:
        font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', font_size)
    except:
        font = ImageFont.load_default()

    texte = 'W'
    bbox  = draw.textbbox((0, 0), texte, font=font)
    tw    = bbox[2] - bbox[0]
    tx    = cx - tw // 2
    ty    = taille - int(taille * 0.22)
    draw.text((tx, ty), texte, fill=(255, 255, 255, 240), font=font)

    # Sauvegarder
    chemin = f'icons/icon-{taille}.png'
    img.save(chemin, 'PNG')
    print(f'✅ Icône créée : {chemin} ({taille}x{taille})')
    return chemin

# Générer toutes les tailles
print('🌿 Génération des icônes WhatAPlant PWA...\n')
for t in TAILLES:
    generer_icone(t)

# Créer un screenshot placeholder
img_sc = Image.new('RGB', (390, 844), (13, 92, 58))
draw_sc = ImageDraw.Draw(img_sc)
draw_sc.rectangle([0, 0, 390, 844], fill=(13, 92, 58))
# Simuler l'interface
draw_sc.rectangle([20, 80, 370, 160], fill=(27, 138, 94), width=0)
draw_sc.rectangle([20, 180, 370, 450], fill=(255, 255, 255), width=0)
draw_sc.rectangle([20, 470, 370, 580], fill=(232, 245, 238), width=0)
img_sc.save('icons/screenshot-mobile.png', 'PNG')
print('✅ Screenshot créé : icons/screenshot-mobile.png')

print('\n🎉 Toutes les icônes ont été générées dans le dossier icons/')
print('Placez le dossier icons/ dans votre projet.')