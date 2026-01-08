import numpy as np
from sklearn.cluster import MiniBatchKMeans
from skimage import color
from PIL import Image
import os

# Protection contre les bombes de décompression
# Limite à environ 80 Megapixels (ex: 9000x9000px)
# Si l'image est plus grande, Pillow lèvera une exception DecompressionBombError
Image.MAX_IMAGE_PIXELS = 80_000_000

def kmeans_quantization(image_path, n_colors):
    """
    Réduit le nombre de couleurs d'une image en utilisant K-Means.
    """
    # 1. Charger l'image
    img = Image.open(image_path).convert('RGB')
    img_np = np.array(img)
    w, h, d = img_np.shape
    
    # 2. Vectorisation
    pixels = img_np.reshape(-1, 3)
    
    # 3. K-Means
    kmeans = MiniBatchKMeans(n_clusters=n_colors, batch_size=2048, n_init=3)
    labels = kmeans.fit_predict(pixels)
    palette = kmeans.cluster_centers_.astype('uint8')
    
    # 4. Reconstruction
    recolored_pixels = palette[labels]
    recolored_img_np = recolored_pixels.reshape(w, h, d)
    
    return Image.fromarray(recolored_img_np)

def calculate_quality_score(original_path, quantized_image):
    """
    Calcule la différence perceptuelle (Delta-E CIEDE2000).
    Plus le chiffre est bas, plus c'est fidèle.
    < 2.3 = Différence à peine visible pour l'œil humain.
    """
    # Optimisation : On redimensionne pour le calcul de métrique (gain de vitesse x100)
    target_size = (256, 256)
    
    # Charger les deux images en RGB
    img_orig = Image.open(original_path).convert('RGB').resize(target_size)
    img_quant = quantized_image.resize(target_size)
    
    # Conversion en espace LAB (Perception humaine)
    lab_orig = color.rgb2lab(np.array(img_orig))
    lab_quant = color.rgb2lab(np.array(img_quant))
    
    # Calcul Delta-E 2000 (Le standard industriel)
    delta_e = color.deltaE_ciede2000(lab_orig, lab_quant)
    
    # On retourne la moyenne de l'erreur sur toute l'image
    return round(float(np.mean(delta_e)), 2)