from flask import Flask, request, jsonify, render_template
from app.worker import process_image_task
import os
import uuid

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = '/tmp/uploads'

# Création du dossier temporaire
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# Simule une base de données Redis pour le Rate Limiting
ip_quotas = {}
MAX_FREE_REQUESTS = 5

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/optimize', methods=['POST'])
def optimize():
    # 1. Vérification Quota (Freemium)
    client_ip = request.remote_addr
    usage = ip_quotas.get(client_ip, 0)
    
    if usage >= MAX_FREE_REQUESTS:
        return jsonify({
            "error": "Quota gratuit dépassé ! Passez à la version Pro.",
            "remaining": 0
        }), 429
    
    # 2. Vérification fichier
    if 'image' not in request.files:
        return jsonify({"error": "Aucune image envoyée"}), 400
        
    file = request.files['image']
    n_colors = int(request.form.get('colors', 8))
    
    # 3. Sauvegarde locale temporaire
    # On utilise un UUID pour éviter les conflits de noms
    ext = file.filename.split('.')[-1]
    filename = f"{uuid.uuid4()}.{ext}"
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(filepath)
    
    # 4. Incrémenter le quota
    ip_quotas[client_ip] = usage + 1
    
    # 5. Lancer la tâche Celery (Asynchrone)
    task = process_image_task.delay(filepath, n_colors)
    
    return jsonify({
        "message": "Traitement démarré",
        "task_id": task.id,
        "quota_used": f"{usage + 1}/{MAX_FREE_REQUESTS}"
    }), 202

@app.route('/api/status/<task_id>')
def status(task_id):
    # Interroge Celery pour savoir où en est la tâche
    task = process_image_task.AsyncResult(task_id)
    
    if task.state == 'PENDING':
        return jsonify({"state": "PROCESSING"}), 200
    elif task.state == 'SUCCESS':
        return jsonify(task.result), 200 # Contient l'URL
    elif task.state == 'FAILURE':
        return jsonify({"state": "FAILURE", "error": str(task.info)}), 500
    
    return jsonify({"state": task.state}), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)