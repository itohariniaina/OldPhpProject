import numpy as np
from sklearn.cluster import MiniBatchKMeans
from PIL import Image
import os

def kmeans_quantization(image_path, n_colors):
    """
    Réduit le nombre de couleurs d'une image en utilisant K-Means.
    """
    # 1. Charger l'image
    img = Image.open(image_path).convert('RGB')
    img_np = np.array(img)
    w, h, d = img_np.shape
    
    # 2. Vectorisation : Transformer l'image en une liste de pixels
    pixels = img_np.reshape(-1, 3)
    
    # 3. K-Means (Trouver les meilleures couleurs)
    # MiniBatchKMeans est beaucoup plus rapide que KMeans standard
    kmeans = MiniBatchKMeans(n_clusters=n_colors, batch_size=2048, n_init=3)
    labels = kmeans.fit_predict(pixels)
    palette = kmeans.cluster_centers_.astype('uint8')
    
    # 4. Reconstruire l'image
    recolored_pixels = palette[labels]
    recolored_img_np = recolored_pixels.reshape(w, h, d)
    
    return Image.fromarray(recolored_img_np)