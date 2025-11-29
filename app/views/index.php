
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quantification des Couleurs</title>
    <link rel="stylesheet" href="/ProjetPHP/app/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Quantification des Couleurs d'une Image</h1>
        
        <form action="/ProjetPHP/public/process-image" method="post" enctype="multipart/form-data">
            <label for="image">Sélectionner une image :</label>
            <input type="file" id="image" name="image" accept="image/*" required>
            
            <label for="nbCouleurs">Nombre de couleurs dans la palette :</label>
            <input type="number" id="nbCouleurs" name="nbCouleurs" min="2" max="256" value="8" required>
            
            <button type="submit">Analyser l'image avec toutes les méthodes</button>
        </form>
        
        <div class="info-box">
            <h2>À propos de cette application</h2>
            <p>Cette application vous permet de réduire le nombre de couleurs d'une image en utilisant trois méthodes différentes :</p>
            <ul>
                <li><strong>Méthode naïve</strong> : Utilise une approche simple de division de l'espace colorimétrique</li>
                <li><strong>Méthode K-means</strong> : Applique l'algorithme de clustering K-means pour trouver les couleurs dominantes</li>
                <li><strong>Méthode Imagick</strong> : Utilise la bibliothèque ImageMagick pour une quantification professionnelle</li>
            </ul>
            <p>Pour commencer, sélectionnez une image et choisissez le nombre de couleurs souhaité dans la palette.</p>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <p><?= htmlspecialchars($_SESSION['error']) ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <p><?= htmlspecialchars($_SESSION['success']) ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; <?= date('Y') ?> - Application de Quantification des Couleurs</p>
    </footer>
    
</body>
</html> 
