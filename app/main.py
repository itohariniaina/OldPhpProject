from flask import Flask, request, jsonify, render_template
from app.worker import process_image_task
import os
import uuid
import redis  # <--- Il faut importer la lib redis

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = '/tmp/uploads'

# Création du dossier temporaire
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# --- CONNEXION À REDIS ---
# On se connecte au service "redis" défini dans docker-compose
# On utilise la db=1 pour ne pas mélanger avec Celery (qui utilise souvent db=0)
r = redis.Redis(host='redis', port=6379, db=1, decode_responses=True)

MAX_FREE_REQUESTS = 5
QUOTA_PERIOD_SECONDS = 86400 # 24 heures

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/optimize', methods=['POST'])
def optimize():
    client_ip = request.remote_addr
    
    # --- 1. GESTION QUOTA VIA REDIS ---
    # On crée une clé unique par IP, ex: "quota:192.168.1.15"
    redis_key = f"quota:{client_ip}"
    
    # On récupère la valeur actuelle (0 si n'existe pas)
    current_usage = r.get(redis_key)
    
    if current_usage is None:
        current_usage = 0
    else:
        current_usage = int(current_usage)
    
    if current_usage >= MAX_FREE_REQUESTS:
        return jsonify({
            "error": "Quota gratuit journalier dépassé ! Passez à la version Pro.",
            "remaining": 0
        }), 429
    
    # --- 2. Vérification fichier ---
    if 'image' not in request.files:
        return jsonify({"error": "Aucune image envoyée"}), 400
        
    file = request.files['image']
    n_colors = int(request.form.get('colors', 8))
    
    # 3. Sauvegarde locale temporaire
    ext = file.filename.split('.')[-1]
    filename = f"{uuid.uuid4()}.{ext}"
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(filepath)
    
    # --- 4. INCRÉMENTATION REDIS ---
    # On incrémente le compteur
    new_usage = r.incr(redis_key)
    
    # Si c'est la première requête (donc new_usage == 1), on met un timer d'expiration
    # Sinon le compteur resterait stocké à vie.
    if new_usage == 1:
        r.expire(redis_key, QUOTA_PERIOD_SECONDS)
    
    # 5. Lancer la tâche Celery
    task = process_image_task.delay(filepath, n_colors)
    
    return jsonify({
        "message": "Traitement démarré",
        "task_id": task.id,
        "quota_used": f"{new_usage}/{MAX_FREE_REQUESTS}"
    }), 202

@app.route('/api/status/<task_id>')
def status(task_id):
    task = process_image_task.AsyncResult(task_id)
    
    if task.state == 'PENDING':
        return jsonify({"state": "PROCESSING"}), 200
    elif task.state == 'SUCCESS':
        return jsonify(task.result), 200
    elif task.state == 'FAILURE':
        return jsonify({"state": "FAILURE", "error": str(task.info)}), 500
    
    return jsonify({"state": task.state}), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)