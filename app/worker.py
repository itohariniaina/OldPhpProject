import os
import boto3
from celery import Celery
from app import logic
from io import BytesIO

# Configuration Celery
celery = Celery('tasks', broker=os.getenv('CELERY_BROKER_URL'),
    backend=os.getenv('CELERY_RESULT_BACKEND'))

# Client S3 (MinIO)
s3 = boto3.client('s3',
    endpoint_url=os.getenv('AWS_ENDPOINT_URL'),
    aws_access_key_id=os.getenv('AWS_ACCESS_KEY_ID'),
    aws_secret_access_key=os.getenv('AWS_SECRET_ACCESS_KEY')
)
BUCKET = os.getenv('S3_BUCKET_NAME')

@celery.task(bind=True)
def process_image_task(self, image_path, n_colors):
    """
    Cette fonction tourne en arrière-plan dans le conteneur 'worker'.
    """
    try:
        # 1. Traitement scientifique
        result_image = logic.kmeans_quantization(image_path, n_colors)
        
        # 2. Préparation pour upload (en mémoire)
        buffer = BytesIO()
        result_image.save(buffer, format="JPEG", quality=85)
        buffer.seek(0)
        
        # 3. Upload vers MinIO
        filename = f"result_{self.request.id}.jpg"
        s3.upload_fileobj(
            buffer, 
            BUCKET, 
            filename,
            ExtraArgs={'ContentType': 'image/jpeg'}
        )
        
        # 4. Nettoyage fichier temporaire
        if os.path.exists(image_path):
            os.remove(image_path)

        # 5. Retourne l'URL publique (localhost pour le test)
        # Note: En prod, ce serait https://s3.amazonaws.com/...
        return {
            'state': 'SUCCESS',
            'url': f"http://localhost:9000/{BUCKET}/{filename}"
        }
        
    except Exception as e:
        return {'state': 'FAILURE', 'error': str(e)}