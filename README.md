# 🎨 Quantize.Studio - Intelligent Image Optimization SaaS

![Python](https://img.shields.io/badge/Python-3.9-blue?style=flat&logo=python)
![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?style=flat&logo=docker)
![Azure](https://img.shields.io/badge/Deployment-Azure-0078D4?style=flat&logo=microsoft-azure)
![Scientific](https://img.shields.io/badge/Metric-CIEDE2000-green)

**Quantize.Studio** est une plateforme SaaS permettant aux professionnels de l'impression (Print-on-Demand) et du e-commerce de réduire les coûts d'encre en optimisant intelligemment les couleurs des images, sans perte visuelle perceptible.

Ce projet est une refonte complète "Cloud-Native" d'une application legacy, passant d'un script PHP séquentiel à une architecture micro-services asynchrone en Python.

---

## 💎 Pourquoi ce projet ? (Value Proposition)

L'impression textile (DTG - Direct to Garment) coûte cher. Imprimer une image de 16 millions de couleurs est inutilement coûteux si l'œil humain n'en perçoit qu'une fraction.

**Notre solution :**

1.  **Réduction Algorithmique :** Utilisation de K-Means dans l'espace colorimétrique **CIELAB** pour ne garder que les couleurs dominantes "perceptuelles".
2.  **Garantie Scientifique :** Calcul automatique du **Delta-E (CIEDE2000)** pour valider mathématiquement la fidélité de l'image.
3.  **Visualisation Produit :** Rendu 3D instantané sur un T-shirt via WebGL (`<model-viewer>`).

---

## ⚙️ Architecture Technique

L'application repose sur une architecture **Micro-services** conteneurisée :

- **API Gateway (Flask)** : Gère les requêtes HTTP, le Rate Limiting (Freemium) et l'upload.
- **Worker Asynchrone (Celery)** : Exécute les calculs lourds (K-Means, Delta-E) en arrière-plan sans bloquer l'interface.
- **Message Broker (Redis)** : Gère la file d'attente des tâches et les quotas utilisateurs.
- **Storage (S3 Compatible)** : Stockage sécurisé des images traitées (MinIO en local, Scaleway en Prod).
- **Infrastructure** : Orchestration via **Docker Compose** en local et **Azure App Service** en production.

### Stack Technologique

- **Backend :** Python 3.9, Flask, Celery.
- **Data Science :** NumPy, Scikit-learn (MiniBatchKMeans), Scikit-image (Delta-E).
- **Frontend :** HTML5, JavaScript (Polling), Google Model-Viewer (3D).
- **DevOps :** Docker, Docker Compose, Azure CLI, GitHub Actions.

---

## 🔬 L'Approche Scientifique (K-Means & CIELAB)

Contrairement aux outils classiques qui réduisent les couleurs dans l'espace RGB (rouge, vert, bleu), notre algorithme effectue une conversion préalable vers l'espace **L\*a\*b\***.

1.  **Conversion RGB -> LAB :** L'espace LAB est conçu pour être "perceptuellement uniforme".
2.  **Clustering :** L'algorithme K-Means regroupe les pixels selon leur distance visuelle réelle et non leur code informatique.
3.  **Validation :** Chaque image générée reçoit un score de qualité (Delta-E).
    - _Delta-E < 2.3_ : Différence imperceptible à l'œil nu (Validé pour impression).

---

## 🚀 Installation & Développement Local

### Prérequis

- Docker & Docker Compose
- Git

### 1. Cloner le projet

```bash
git clone [https://github.com/votre-username/quantize-studio.git](https://github.com/votre-username/quantize-studio.git)
cd quantize-studio
```

### 2. Configuration (.env)

Créez un fichier `.env` à la racine (ne pas commiter ce fichier) :

```ini
# Configuration Locale (Docker)
FLASK_DEBUG=1
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/1

# Stockage MinIO (Local S3)
AWS_ENDPOINT_URL=http://minio:9000
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
S3_BUCKET_NAME=images-upload

```

### 3. Lancer l'application

```bash
docker-compose up --build

```

- **Application Web :** `http://localhost:5001`
- **Console MinIO :** `http://localhost:9001` (User/Pass: `minioadmin`)

---

## ☁️ Déploiement Cloud (Microsoft Azure)

Ce projet déploie une architecture **Hybrid Cloud** pour optimiser les coûts : le calcul est effectué sur **Azure**, le stockage sur **Scaleway** (S3).

### 1. Création de l'infrastructure Azure

```bash
# Créer le groupe de ressources
az group create --name QuantizeRG --location francecentral

# Créer le registre Docker (ACR)
az acr create --resource-group QuantizeRG --name <VOTRE_REGISTRE> --sku Basic --admin-enabled true

```

### 2. Build & Push de l'image

```bash
az acr login --name <VOTRE_REGISTRE>
docker build -t quantize-img .
docker tag quantize-img <VOTRE_REGISTRE>.azurecr.io/quantize-img:v1
docker push <VOTRE_REGISTRE>.azurecr.io/quantize-img:v1

```

### 3. Configuration de l'Orchestration

Créez un fichier `docker-compose-azure.yml` :

```yaml
version: "3.8"
services:
  web:
    image: <VOTRE_REGISTRE>.azurecr.io/quantize-img:v1
    ports: ["80:5000"]
    environment:
      - WEBSITES_PORT=5000
      - CELERY_BROKER_URL=redis://redis:6379/0
      - CELERY_RESULT_BACKEND=redis://redis:6379/1
      - AWS_ENDPOINT_URL=[https://s3.fr-par.scw.cloud](https://s3.fr-par.scw.cloud)
      - AWS_ACCESS_KEY_ID=VOTRE_ACCESS_KEY
      - AWS_SECRET_ACCESS_KEY=VOTRE_SECRET_KEY
      - S3_BUCKET_NAME=nom-du-bucket-prod
  worker:
    image: <VOTRE_REGISTRE>.azurecr.io/quantize-img:v1
    command: celery -A app.worker.celery worker --loglevel=info
    environment:
      # Mêmes variables que web
  redis:
    image: redis:alpine
```

### 4. Déploiement sur App Service

```bash
# Créer le plan (B1 est inclus dans Azure for Students)
az appservice plan create --name QuantizePlan --resource-group QuantizeRG --sku B1 --is-linux

# Créer la Web App Multi-conteneurs
az webapp create --resource-group QuantizeRG --plan QuantizePlan --name <NOM_APP> --multicontainer-config-type compose --multicontainer-config-file docker-compose-azure.yml

# Lier au registre (Credentials)
az webapp config container set --name <NOM_APP> --resource-group QuantizeRG \
--docker-custom-image-name <VOTRE_REGISTRE>.azurecr.io/quantize-img:v1 \
--docker-registry-server-url https://<VOTRE_REGISTRE>.azurecr.io \
--docker-registry-server-user <VOTRE_REGISTRE> \
--docker-registry-server-password <PASSWORD>

```

---

## Roadmap & Améliorations

- [x] Migration PHP vers Python/NumPy
- [x] Architecture Docker Asynchrone
- [x] Support S3 / MinIO
- [ ] Visualisation 3D
- [ ] Créations de comptes / Types de service (crédits)
      => Ajout d'une base de données
- [ ] Déploiement sur Microsoft Azure
- [ ] Intégration Stripe pour les paiements
- [ ] API Publique avec Clé développeur

---

## 👤 Auteur

Projet développé par **ito.hariniaina** .
Contact : [rak.hariniainaitokiana@gmail.com](mailto:rak.hariniainaitokiana@gmail.com)
