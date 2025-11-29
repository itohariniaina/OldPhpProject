# Utilisation d'une image Python légère
FROM python:3.9-slim

# Installation des dépendances système nécessaires pour le traitement d'image
RUN apt-get update && apt-get install -y \
    libgomp1 \
    && rm -rf /var/lib/apt/lists/*

# Dossier de travail
WORKDIR /app

# Copie des dépendances et installation
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copie du code source
COPY app/ ./app/

# Création des dossiers pour uploads/outputs
RUN mkdir -p /app/static/uploads /app/static/output

# Exposition du port
EXPOSE 5000

# Commande de lancement
CMD ["python", "-m", "app.main"]