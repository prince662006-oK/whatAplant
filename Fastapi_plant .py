"""
fastapi_plant.py
Serveur FastAPI + OpenCV pour analyser les images de plantes
Installation: pip install fastapi uvicorn opencv-python-headless pillow numpy python-multipart requests
Lancement: uvicorn fastapi_plant:app --host 0.0.0.0 --port 8000
"""

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import cv2
import numpy as np
from PIL import Image
import io
import base64
import json
from typing import Optional
import uvicorn

app = FastAPI(title="WhatAPlant — Vision API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

def get_dominant_colors(img_bgr: np.ndarray, k: int = 5) -> list:
    pixels = img_bgr.reshape(-1, 3).astype(np.float32)
    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 100, 0.2)
    _, labels, centers = cv2.kmeans(pixels, k, None, criteria, 10, cv2.KMEANS_RANDOM_CENTERS)
    centers = centers.astype(int)
    counts = np.bincount(labels.flatten())
    sorted_idx = np.argsort(-counts)
    return [{"bgr": centers[i].tolist(), "pct": round(counts[i]/len(labels)*100, 1)} for i in sorted_idx]

def analyze_greenness(img_bgr: np.ndarray) -> dict:
    hsv = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2HSV)
    # Vert clair à vert foncé
    mask1 = cv2.inRange(hsv, np.array([30, 40, 40]), np.array([90, 255, 255]))
    # Vert jaunâtre
    mask2 = cv2.inRange(hsv, np.array([20, 30, 30]), np.array([35, 255, 255]))
    combined = cv2.bitwise_or(mask1, mask2)
    green_ratio = np.sum(combined > 0) / combined.size
    return {"green_ratio": round(green_ratio, 3), "is_plant_likely": green_ratio > 0.1}

def analyze_leaf_texture(img_bgr: np.ndarray) -> dict:
    """Détecte des nervures et textures caractéristiques de feuilles"""
    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    # Laplacian pour détecter les bords
    lap = cv2.Laplacian(gray, cv2.CV_64F)
    lap_var = lap.var()
    # Canny pour compter les bords
    edges = cv2.Canny(gray, 50, 150)
    edge_density = np.sum(edges > 0) / edges.size
    return {
        "texture_variance": round(float(lap_var), 2),
        "edge_density": round(float(edge_density), 4),
        "has_leaf_pattern": lap_var > 200 and edge_density > 0.05
    }

def detect_shapes(img_bgr: np.ndarray) -> dict:
    """Détecte formes rondes, oblongues, etc."""
    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    
    shapes = []
    for cnt in contours:
        area = cv2.contourArea(cnt)
        if area < 500: continue
        perimeter = cv2.arcLength(cnt, True)
        if perimeter == 0: continue
        circularity = 4 * np.pi * area / (perimeter * perimeter)
        x, y, w, h = cv2.boundingRect(cnt)
        aspect_ratio = w / h if h > 0 else 0
        shapes.append({
            "area": int(area),
            "circularity": round(float(circularity), 3),
            "aspect_ratio": round(float(aspect_ratio), 2)
        })
    
    shapes.sort(key=lambda s: s['area'], reverse=True)
    return {"count": len(shapes), "main_shapes": shapes[:3]}

def classify_plant(greenness: dict, texture: dict, colors: list, shapes: dict) -> dict:
    """Classification heuristique basée sur les features visuelles"""
    confidence = 0.0
    plant_type = "plante inconnue"
    description = ""

    green_ratio = greenness.get("green_ratio", 0)
    has_pattern = texture.get("has_leaf_pattern", False)
    edge_density = texture.get("edge_density", 0)

    if greenness.get("is_plant_likely"):
        confidence += 0.3

    if has_pattern:
        confidence += 0.25
        plant_type = "feuille ou plante herbacée"

    for c in colors[:3]:
        bgr = c['bgr']
        b, g, r = bgr
        # Très vert → feuille fraîche
        if g > r and g > b and g > 80:
            confidence += 0.15
            if g > 120:
                plant_type = "feuille fraîche verte"
            else:
                plant_type = "feuille ou tige"
        # Brun/jaune → plante séchée ou racine
        elif r > g > b and r > 100:
            plant_type = "plante séchée, racine ou écorce"
            confidence += 0.1
        # Très coloré → fleur ou fruit
        elif r > 150 and g < 100:
            plant_type = "fleur rouge ou fruit rouge"
            confidence += 0.1
        elif r > 150 and g > 100 and b < 80:
            plant_type = "fruit ou fleur jaune/orange"
            confidence += 0.1

    # Formes rondes → fruits ou fleurs
    if shapes['count'] > 0:
        main = shapes['main_shapes'][0] if shapes['main_shapes'] else {}
        circ = main.get('circularity', 0)
        ar   = main.get('aspect_ratio', 1)
        if circ > 0.7:
            plant_type = "fruit rond ou fleur"
            confidence += 0.1
        elif ar > 2.5 or ar < 0.4:
            plant_type = "feuille allongée ou tige"
            confidence += 0.05

    confidence = min(confidence, 0.92)

    return {
        "detected_plant": plant_type,
        "confidence": round(confidence, 2),
        "description": f"Image analysée : {plant_type} détecté(e) avec {round(confidence*100)}% de confiance.",
        "features": {
            "green_ratio": green_ratio,
            "has_leaf_pattern": has_pattern,
            "edge_density": edge_density,
            "dominant_colors": colors[:3]
        }
    }

@app.post("/analyze")
async def analyze_plant_image(file: UploadFile = File(...)):
    """
    Analyse une image de plante avec OpenCV
    Retourne les features visuelles et une classification heuristique
    """
    # Validation
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Le fichier doit être une image")
    
    contents = await file.read()
    if len(contents) > 10 * 1024 * 1024:  # 10MB max
        raise HTTPException(status_code=400, detail="Image trop grande (max 10MB)")

    try:
        # Décoder l'image
        nparr = np.frombuffer(contents, np.uint8)
        img_bgr = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        
        if img_bgr is None:
            raise HTTPException(status_code=400, detail="Impossible de lire l'image")

        # Redimensionner si trop grande
        h, w = img_bgr.shape[:2]
        if max(h, w) > 1200:
            scale = 1200 / max(h, w)
            img_bgr = cv2.resize(img_bgr, (int(w*scale), int(h*scale)))

        # Analyses OpenCV
        greenness = analyze_greenness(img_bgr)
        texture   = analyze_leaf_texture(img_bgr)
        colors    = get_dominant_colors(img_bgr)
        shapes    = detect_shapes(img_bgr)
        result    = classify_plant(greenness, texture, colors, shapes)

        # Image avec annotations (contours détectés)
        annotated = img_bgr.copy()
        gray   = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
        edges  = cv2.Canny(gray, 50, 150)
        contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        cv2.drawContours(annotated, contours, -1, (0, 200, 100), 1)

        _, buffer = cv2.imencode('.jpg', annotated, [cv2.IMWRITE_JPEG_QUALITY, 80])
        annotated_b64 = base64.b64encode(buffer).decode('utf-8')

        return {
            **result,
            "width": w,
            "height": h,
            "annotated_image_b64": annotated_b64
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur d'analyse : {str(e)}")

@app.get("/health")
async def health():
    return {"status": "ok", "service": "WhatAPlant Vision API"}

if __name__ == "__main__":
    uvicorn.run("fastapi_plant:app", host="0.0.0.0", port=8000, reload=True)