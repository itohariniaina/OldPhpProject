import os
import numpy as np
from PIL import Image
from sklearn.cluster import MiniBatchKMeans
from skimage import color
from skimage.metrics import structural_similarity
import time

def load_image(path):
    # Convertir en RGB pour s'assurer de la compatibilité (supprimer transparence)
    return Image.open(path).convert('RGB')

def get_image_data(image):
    """Convertit l'image en array NumPy normalisé"""
    return np.array(image)

def naive_quantization(image_path, n_colors):
    """
    Équivalent Méthode Naïve : Utilise 'quantize' de PIL (Fast Octree) 
    ou histogramme simple, mais ici on utilise une méthode rapide de PIL.
    """
    img = load_image(image_path)
    start = time.time()
    
    # PIL utilise Fast Octree ou Median Cut pour quantize
    quantized = img.quantize(colors=n_colors, method=Image.Quantize.MAXCOVERAGE).convert('RGB')
    
    # Extraction de la palette
    palette_img = quantized.resize((1, 1), resample=0) # Astuce pour chopper la palette n'est pas directe en RGB
    # On récupère les couleurs uniques de l'image quantifiée
    q_np = np.array(quantized)
    colors = np.unique(q_np.reshape(-1, 3), axis=0)
    
    # Si on a plus de couleurs que demandé (rare avec quantize), on coupe
    if len(colors) > n_colors:
        colors = colors[:n_colors]
        
    return quantized, colors, time.time() - start

def kmeans_quantization(image_path, n_colors):
    """
    Équivalent Méthode K-means : Utilise Scikit-Learn (beaucoup plus rapide que le PHP manuel)
    """
    img = load_image(image_path)
    img_np = np.array(img)
    w, h, d = img_np.shape
    
    # Aplatir l'image (pixels, 3)
    pixels = img_np.reshape(-1, 3)
    
    start = time.time()
    # MiniBatchKMeans est plus rapide que KMeans classique pour les images
    kmeans = MiniBatchKMeans(n_clusters=n_colors, n_init=3, batch_size=2048)
    labels = kmeans.fit_predict(pixels)
    palette = kmeans.cluster_centers_.astype('uint8')
    
    # Reconstruction de l'image
    recolored_pixels = palette[labels]
    recolored_img = recolored_pixels.reshape(w, h, d)
    
    return Image.fromarray(recolored_img), palette, time.time() - start

def imagick_equivalent(image_path, n_colors):
    """
    Équivalent Méthode Imagick : On utilise PIL.quantize avec une méthode différente (MEDIANCUT)
    qui est souvent utilisée par les outils pro comme ImageMagick.
    """
    img = load_image(image_path)
    start = time.time()
    
    # Méthode Median Cut
    quantized = img.quantize(colors=n_colors, method=Image.Quantize.MEDIANCUT).convert('RGB')
    
    q_np = np.array(quantized)
    colors = np.unique(q_np.reshape(-1, 3), axis=0)
    
    return quantized, colors, time.time() - start

def calculate_error_ciede2000(original_path, recolored_img):
    """
    Calcul du Delta-E (CIEDE2000). 
    En Python + Scikit-image, c'est vectorisé, donc rapide.
    """
    # Charger et redimensionner pour que le calcul ne prenne pas 10 ans
    # On travaille sur une version réduite pour la métrique (comme dans votre PHP avec $sampleRate)
    target_size = (200, 200)
    
    img1 = load_image(original_path).resize(target_size)
    img2 = recolored_img.resize(target_size)
    
    # Conversion RGB -> LAB (Espace Lab requis pour Delta E)
    lab1 = color.rgb2lab(np.array(img1))
    lab2 = color.rgb2lab(np.array(img2))
    
    # Calcul Delta E CIEDE2000
    delta_e = color.deltaE_ciede2000(lab1, lab2)
    
    # Moyenne de l'erreur
    return np.mean(delta_e)

def create_palette_image(colors_array, swatch_size=50):
    """Crée une bandelette d'image montrant la palette"""
    if len(colors_array) == 0:
        return Image.new('RGB', (swatch_size, swatch_size))
        
    width = len(colors_array) * swatch_size
    height = swatch_size
    palette_img = Image.new('RGB', (width, height))
    
    for i, col in enumerate(colors_array):
        # S'assurer que la couleur est un tuple d'entiers
        c = tuple(map(int, col))
        img_col = Image.new('RGB', (swatch_size, swatch_size), color=c)
        palette_img.paste(img_col, (i * swatch_size, 0))
        
    return palette_img