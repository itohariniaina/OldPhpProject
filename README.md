# 🎨 Système de Quantification des Couleurs (Python Edition)

> **Modernisation d'un projet legacy PHP vers Python** | **Traitement d'images vectorisé** | **Architecture Dockerisée**

[![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://www.python.org/)
[![Flask](https://img.shields.io/badge/Flask-000000?style=for-the-badge&logo=flask&logoColor=white)](https://flask.palletsprojects.com/)
[![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)
[![NumPy](https://img.shields.io/badge/NumPy-013243?style=for-the-badge&logo=numpy&logoColor=white)](https://numpy.org/)
[![Scikit-Learn](https://img.shields.io/badge/scikit--learn-F7931E?style=for-the-badge&logo=scikit-learn&logoColor=white)](https://scikit-learn.org/)

👉 **[Accéder au projet GitLab](https://forge.univ-lyon1.fr/p2202482/projetphp)**

---

## 🎯 À propos du projet

Ce projet est une **refonte complète** d'un ancien système de quantification de couleurs PHP. L'objectif était de migrer d'une architecture impérative lente vers une architecture **Python vectorisée** et conteneurisée.

L'application permet de réduire le nombre de couleurs d'une image (Quantification) tout en minimisant la perte de qualité visuelle perceptuelle (Delta-E).

### ✨ Améliorations de la version Python

🚀 **Performance Extrême**

- **Avant (PHP)** : Boucles `for` imbriquées sur les pixels (O(n\*k)).
- **Après (Python)** : Opérations matricielles via **NumPy** et implémentations C-optimized via **Scikit-learn**.

🔬 **Méthodes de Quantification**

- **Méthode Naïve** : Algorithme _Fast Octree_ (via PIL/Pillow).
- **Algorithme K-means** : Clustering vectorisé avec `MiniBatchKMeans` (Scikit-learn).
- **Méthode Pro** : Algorithme _Median Cut_ (Standard industriel).

📊 **Métriques Scientifiques**

- Calcul du **Delta-E CIEDE2000** via `skimage` (beaucoup plus précis et rapide que l'implémentation manuelle).

---

## 🛠️ Stack Technique

### Backend & Science des Données

![Python](https://img.shields.io/badge/Python_3.9+-3776AB?style=flat-square&logo=python&logoColor=white)
![Flask](https://img.shields.io/badge/Flask-000000?style=flat-square&logo=flask&logoColor=white)
![NumPy](https://img.shields.io/badge/NumPy-013243?style=flat-square&logo=numpy&logoColor=white)
![Pillow](https://img.shields.io/badge/Pillow-Image_Processing-blue?style=flat-square)

### Infrastructure & DevOps

![Docker](https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white)
![Docker Compose](https://img.shields.io/badge/Docker_Compose-2496ED?style=flat-square&logo=docker&logoColor=white)

### Frontend

![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)
![Jinja2](https://img.shields.io/badge/Jinja2-Templates-B41717?style=flat-square)

---

## 🚀 Installation & Démarrage

Le projet est entièrement conteneurisé. Vous n'avez besoin que de Docker.

### Pré-requis

- Docker Desktop & Docker Compose

### Lancement rapide

```bash
# 1. Cloner le projet
git clone [https://forge.univ-lyon1.fr/p2202482/projetphp.git](https://forge.univ-lyon1.fr/p2202482/projetphp.git)
cd projetphp

# 2. Lancer l'environnement (Backend Flask)
docker-compose up --build
```

L'application sera accessible sur : **http://localhost:5001**

---

## 💡 Innovation Technique : PHP vs Python

### 1\. K-Means Clustering

Le passage à Python permet d'utiliser `MiniBatchKMeans` qui est optimisé en C et utilise le parallélisme CPU, contrairement à l'implémentation PHP pure.

```python
# Python (Vectorisé - Scikit Learn)
# Traite l'image entière comme une matrice (h*w, 3) en une fraction de seconde
kmeans = MiniBatchKMeans(n_clusters=n_colors, batch_size=2048)
labels = kmeans.fit_predict(pixels)
palette = kmeans.cluster_centers_.astype('uint8')
```

### 2\. Calcul Delta-E (Qualité Perceptuelle)

L'utilisation de `scikit-image` permet de calculer la différence perceptuelle sur l'ensemble de l'image sans boucles explicites.

```python
# Conversion et calcul vectorisé RGB -> LAB -> DeltaE
lab1 = color.rgb2lab(img1)
lab2 = color.rgb2lab(img2)
delta_e = color.deltaE_ciede2000(lab1, lab2) # Résultat immédiat
```

### 3\. Architecture Docker

Fini les configurations WAMP/XAMPP complexes. Le `Dockerfile` gère l'environnement d'exécution.

```dockerfile
FROM python:3.9-slim
RUN apt-get install -y libgomp1 # Support OpenMP pour Scikit-learn
COPY requirements.txt .
RUN pip install -r requirements.txt
CMD ["python", "-m", "app.main"]
```

---

## 📈 Nouvelle Architecture du Projet

```
🎨 Projet Python/Flask
├── 🐳 docker-compose.yml    # Orchestration
├── 🐳 Dockerfile            # Image Python optimisée
├── 🐍 app/
│   ├── __init__.py
│   ├── main.py              # Contrôleur Flask (Routes)
│   ├── logic.py             # Logique Métier (NumPy, Sklearn, PIL)
│   ├── static/
│   │   ├── css/
│   │   ├── uploads/         # Stockage temporaire (Volume Docker)
│   │   └── output/          # Résultats générés
│   └── templates/           # Vues Jinja2 (HTML)
│       ├── index.html
│       └── results.html
└── 📄 requirements.txt      # Dépendances Python
```

---

## 🔬 Résultats & Performance

La migration a permis des gains de performances significatifs :

| Métrique                     | Version PHP Legacy    | Version Python (Actuelle) | Gain    |
| ---------------------------- | --------------------- | ------------------------- | ------- |
| **Temps K-Means (Image 4K)** | \~15-30 secondes      | **\< 2 secondes**         | **x15** |
| **Précision Delta-E**        | Approximation         | **CIEDE2000 Exact**       | ++      |
| **Déploiement**              | Complexe (Apache/PHP) | **1 commande Docker**     | ++      |

---

## 📧 Contact

**Développé par Hariniaina Itokiana**
_Projet de modernisation technique - BUT Informatique Lyon 1_

[](mailto:rak.hariniainaitokiana@gmail.com)
[](https://www.linkedin.com/in/hariniaina-itokiana-rak/)

**📍 Basé à Lyon — Ouvert à la mobilité**

```

```

```

```
