import os
import uuid
from flask import Flask, render_template, request, redirect, url_for, flash
from werkzeug.utils import secure_filename
from app import logic

app = Flask(__name__)
app.secret_key = 'votre_cle_secrete_super_securisee'

# Configuration des chemins
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_FOLDER = os.path.join(BASE_DIR, 'static', 'uploads')
OUTPUT_FOLDER = os.path.join(BASE_DIR, 'static', 'output')

os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(OUTPUT_FOLDER, exist_ok=True)

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['OUTPUT_FOLDER'] = OUTPUT_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024 # 16MB max

ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'gif'}

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/process', methods=['POST'])
def process_image():
    if 'image' not in request.files:
        flash('Aucun fichier sélectionné')
        return redirect(url_for('index'))
    
    file = request.files['image']
    if file.filename == '':
        flash('Aucun fichier sélectionné')
        return redirect(url_for('index'))
        
    if file and allowed_file(file.filename):
        # Nettoyage et sauvegarde
        filename = secure_filename(file.filename)
        # On utilise un ID unique pour éviter les conflits
        unique_id = str(uuid.uuid4())[:8]
        base_name = f"source_{unique_id}.jpg"
        source_path = os.path.join(app.config['UPLOAD_FOLDER'], base_name)
        
        # Sauvegarder (convertir en jpg pour simplifier)
        img = logic.load_image(file)
        img.save(source_path)
        
        nb_couleurs = int(request.form.get('nbCouleurs', 8))
        nb_couleurs = max(2, min(nb_couleurs, 256))
        
        results = {}
        
        try:
            # 1. Méthode Naïve
            img_naive, pal_naive, t_naive = logic.naive_quantization(source_path, nb_couleurs)
            img_naive.save(os.path.join(app.config['OUTPUT_FOLDER'], f'recoloriee_naive_{unique_id}.jpg'))
            logic.create_palette_image(pal_naive).save(os.path.join(app.config['OUTPUT_FOLDER'], f'palette_naive_{unique_id}.jpg'))
            results['naive'] = {
                'error': logic.calculate_error_ciede2000(source_path, img_naive),
                'time': t_naive
            }

            # 2. Méthode K-Means
            img_kmeans, pal_kmeans, t_kmeans = logic.kmeans_quantization(source_path, nb_couleurs)
            img_kmeans.save(os.path.join(app.config['OUTPUT_FOLDER'], f'recoloriee_kmeans_{unique_id}.jpg'))
            logic.create_palette_image(pal_kmeans).save(os.path.join(app.config['OUTPUT_FOLDER'], f'palette_kmeans_{unique_id}.jpg'))
            results['kmeans'] = {
                'error': logic.calculate_error_ciede2000(source_path, img_kmeans),
                'time': t_kmeans
            }

            # 3. Méthode "Pro" (Imagick equivalent -> Median Cut)
            img_pro, pal_pro, t_pro = logic.imagick_equivalent(source_path, nb_couleurs)
            img_pro.save(os.path.join(app.config['OUTPUT_FOLDER'], f'recoloriee_imagick_{unique_id}.jpg'))
            logic.create_palette_image(pal_pro).save(os.path.join(app.config['OUTPUT_FOLDER'], f'palette_imagick_{unique_id}.jpg'))
            results['imagick'] = {
                'error': logic.calculate_error_ciede2000(source_path, img_pro),
                'time': t_pro
            }
            
            # Déterminer la meilleure méthode (plus petite erreur)
            best_method = min(results, key=lambda k: results[k]['error'])
            
            return render_template('results.html', 
                                   processed=True,
                                   id=unique_id,
                                   nb_couleurs=nb_couleurs,
                                   results=results,
                                   best_method=best_method)

        except Exception as e:
            flash(f"Erreur lors du traitement: {str(e)}")
            return redirect(url_for('index'))
            
    return redirect(url_for('index'))

if __name__ == '__main__':
    # Mode dev uniquement, Docker lancera via python -m app.main
    app.run(host='0.0.0.0', port=5000, debug=True)