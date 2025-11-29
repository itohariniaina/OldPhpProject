<?php
// Protection contre l'accès direct
if (!defined('APP_PATH')) {
    exit('Accès direct au fichier interdit');
}

// Récupération des paramètres de traitement depuis la session ou l'URL
$processed = isset($_GET['processed']) ? $_GET['processed'] === 'true' : false;
$nbCouleurs = isset($_GET['nbCouleurs']) ? (int)$_GET['nbCouleurs'] : 8;
$erreurNaive = isset($_GET['erreur_naive']) ? $_GET['erreur_naive'] : null;
$erreurKmeans = isset($_GET['erreur_kmeans']) ? $_GET['erreur_kmeans'] : null;
$erreurImagick = isset($_GET['erreur_imagick']) ? $_GET['erreur_imagick'] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats - Quantification des Couleurs</title>
    <link rel="stylesheet" href="/ProjetPHP/app/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Quantification des Couleurs d'une Image</h1>
        
        <?php if ($processed): ?>
            <div class="original-image">
                <h2>Image originale</h2>
                <img src="<?= APP_URL ?>/../uploads/source.jpg" alt="Image originale">
            </div>
            
            <div class="methods-container">
                <!-- Méthode Naïve -->
                <div class="method-column">
                    <h2>Méthode naïve (<?= $nbCouleurs ?> couleurs)</h2>
                    <div class="result-item">
                        <h3>Palette de couleurs</h3>
                        <img src="<?= APP_URL ?>/../output/palette_naive.jpg" alt="Palette naïve">
                    </div>
                    <div class="result-item">
                        <h3>Image recoloriée</h3>
                        <img src="<?= APP_URL ?>/../output/recoloriee_naive.jpg" alt="Image recoloriée (naïve)">
                        <?php if ($erreurNaive !== null): ?>
                            <p>Erreur moyenne : <span class="error-value"><?= number_format((float)$erreurNaive, 6) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Méthode K-means -->
                <div class="method-column">
                    <h2>Méthode K-means (<?= $nbCouleurs ?> couleurs)</h2>
                    <div class="result-item">
                        <h3>Palette de couleurs</h3>
                        <img src="<?= APP_URL ?>/../output/palette_kmeans.jpg" alt="Palette k-means">
                    </div>
                    <div class="result-item">
                        <h3>Image recoloriée</h3>
                        <img src="<?= APP_URL ?>/../output/recoloriee_kmeans.jpg" alt="Image recoloriée (k-means)">
                        <?php if ($erreurKmeans !== null): ?>
                            <p>Erreur moyenne : <span class="error-value"><?= number_format((float)$erreurKmeans, 6) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Méthode Imagick -->
                <div class="method-column">
                    <h2>Méthode Imagick (<?= $nbCouleurs ?> couleurs)</h2>
                    <div class="result-item">
                        <h3>Palette de couleurs</h3>
                        <img src="<?= APP_URL ?>/../output/palette_imagick.jpg" alt="Palette Imagick">
                    </div>
                    <div class="result-item">
                        <h3>Image recoloriée</h3>
                        <img src="<?= APP_URL ?>/../output/recoloriee_imagick.jpg" alt="Image recoloriée (Imagick)">
                        <?php if ($erreurImagick !== null): ?>
                            <p>Erreur moyenne : <span class="error-value"><?= number_format((float)$erreurImagick, 6) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <a href="<?= APP_URL ?>" class="btn btn-primary">Traiter une nouvelle image</a>
            </div>
            
            <div class="comparison">
                <h2>Comparaison des méthodes</h2>
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Méthode</th>
                            <th>Erreur moyenne</th>
                            <th>Performance relative</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $errors = array_filter([
                            'naive' => $erreurNaive !== null ? (float)$erreurNaive : null,
                            'kmeans' => $erreurKmeans !== null ? (float)$erreurKmeans : null,
                            'imagick' => $erreurImagick !== null ? (float)$erreurImagick : null
                        ]);
                        if (!empty($errors)) {
                            $bestMethod = array_keys($errors, min($errors))[0];
                            foreach ($errors as $method => $error):
                                $performanceClass = ($method === $bestMethod) ? 'best-performance' : '';
                        ?>
                        <tr class="<?= $performanceClass ?>">
                            <td><?= ucfirst($method) ?></td>
                            <td><?= number_format($error, 6) ?></td>
                            <td>
                                <?php if ($method === $bestMethod): ?>
                                    <strong>Meilleure méthode</strong>
                                <?php else: ?>
                                    <?= number_format(($error / $errors[$bestMethod] - 1) * 100, 2) ?>% moins précis
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="method-explanation">
                <h2>Explication des méthodes</h2>
                <div class="explanation-item">
                    <h3>Méthode naïve</h3>
                    <p>Cette méthode simple consiste à sélectionner les couleurs les plus fréquentes dans l'image pour créer la palette. Elle est rapide mais ne prend pas en compte la distribution spatiale ou perceptuelle des couleurs.</p>
                </div>
                <div class="explanation-item">
                    <h3>Méthode K-means</h3>
                    <p>L'algorithme K-means regroupe les couleurs en clusters dans l'espace colorimétrique. Cette méthode est plus sophistiquée et tente de minimiser la distance perceptuelle entre les couleurs originales et la palette réduite.</p>
                </div>
                <div class="explanation-item">
                    <h3>Méthode Imagick</h3>
                    <p>Utilisant l'extension ImageMagick de PHP, cette méthode implémente des algorithmes avancés de quantification de couleurs qui prennent en compte la perception humaine des couleurs en utilisant l'espace colorimétrique LAB.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="no-results">
                <p>Aucun résultat à afficher. Veuillez <a href="<?= APP_URL ?>">traiter une image</a> d'abord.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>