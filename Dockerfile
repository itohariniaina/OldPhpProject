FROM python:3.9-slim

# Installation des dépendances système
RUN apt-get update && apt-get install -y libgomp1 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# --- 🔒 SÉCURITÉ 5 : UTILISATEUR NON-ROOT ---
# 1. Création de l'utilisateur 'appuser'
RUN useradd -m appuser

# 2. Copie du code
COPY app/ ./app/

# 3. Création du dossier temporaire et attribution des droits À L'UTILISATEUR
# C'est crucial pour éviter les erreurs "Permission Denied"
RUN mkdir -p /tmp/uploads && chown -R appuser:appuser /tmp/uploads

# 4. Passage sur l'utilisateur limité
USER appuser

ENV PYTHONPATH=/app

CMD ["gunicorn", "--bind", "0.0.0.0:5000", "app.main:app"]