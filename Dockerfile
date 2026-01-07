FROM python:3.9-slim

# Installation des dépendances système pour le traitement d'image (OpenMP)
RUN apt-get update && apt-get install -y libgomp1 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copie du code
COPY app/ ./app/

# Création dossier temporaire pour uploads
RUN mkdir -p /tmp/uploads

# Variable d'environnement pour que Python trouve le module 'app'
ENV PYTHONPATH=/app

CMD ["python", "-m", "app.main"]