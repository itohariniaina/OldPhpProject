<?php
namespace App\Models;
use App\Models\ErrorCalculator;
use App\Models\ImageRecolorer;

class ColorPalette {
    private $errorCalculator;
    public function __construct() {
        $this->errorCalculator = new ErrorCalculator();
    }
    
    /**
     * Génère une palette "naïve" basée sur les couleurs les plus fréquentes
     * 
     * @param array $colors Tableau de couleurs avec leur fréquence
     * @param int $k Nombre de couleurs à retenir
     * @return array Palette de couleurs
     */
    public function generateNaivePalette($colors, $k) {
        // Trier les couleurs par fréquence décroissante
        uasort($colors, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Prendre les k couleurs les plus fréquentes
        $palette = [];
        $i = 0;
        foreach ($colors as $color) {
            if ($i >= $k) break;
            $palette[] = $color['rgb'];
            $i++;
        }
        
        return $palette;
    }
    
    /**
     * Génère une palette à l'aide de l'algorithme K-means
     * 
     * @param array $colors Tableau de couleurs avec leur fréquence
     * @param int $k Nombre de couleurs à retenir
     * @param int $maxIterations Nombre maximum d'itérations
     * @return array Palette de couleurs
     */
    public function generateKmeansPalette($colors, $k, $maxIterations = 5) {
        // Extraire les couleurs RGB et leurs fréquences
        $points = [];
        $weights = [];
        
        // Limiter le nombre de points pour accélérer le k-means
        $maxPoints = 2000; // Limiter à 2000 couleurs uniques pour l'analyse
        $i = 0;
        
        foreach ($colors as $colorStr => $info) {
            $points[] = $info['rgb'];
            $weights[] = $info['count'];
            $i++;
            if ($i >= $maxPoints) break;
        }
        
        // Nombre total de points
        $numPoints = count($points);
        if ($numPoints < $k) {
            // Si on a moins de points que de clusters, retourner tous les points
            return array_slice($points, 0, $k);
        }
        
        // Initialiser les centroides en choisissant k couleurs aléatoires pondérées par leur fréquence
        $centroids = [];
        $indices = array_keys($points);
        
        // Sélection aléatoire pondérée des centroides initiaux
        for ($i = 0; $i < $k; $i++) {
            // Calculer la somme des poids restants
            $sum = array_sum(array_map(function($idx) use ($weights) {
                return $weights[$idx];
            }, $indices));
            
            if ($sum <= 0) break;
            
            // Sélection aléatoire pondérée
            $rand = mt_rand(0, $sum - 1);
            $cumul = 0;
            
            foreach ($indices as $key => $idx) {
                $cumul += $weights[$idx];
                if ($cumul > $rand) {
                    $centroids[] = $points[$idx];
                    // Retirer l'indice sélectionné pour éviter les doublons
                    unset($indices[$key]);
                    break;
                }
            }
        }
        
        // S'assurer qu'on a k centroides
        if (count($centroids) < $k) {
            // Compléter aléatoirement si nécessaire
            $missing = $k - count($centroids);
            for ($i = 0; $i < $missing; $i++) {
                if (empty($indices)) break;
                $idx = array_rand($indices);
                $centroids[] = $points[$indices[$idx]];
                unset($indices[$idx]);
            }
        }
        
        // Cache pour les distances calculées
        $distanceCache = [];
        
        // Tableau pour stocker l'appartenance de chaque point à un cluster
        $clusters = array_fill(0, $numPoints, 0);
        $iterations = 0;
        $changesMade = true;
        
        // Itérer jusqu'à convergence ou nombre max d'itérations atteint
        while ($changesMade && $iterations < $maxIterations) {
            $changesMade = false;
            $iterations++;
            
            // Vider le cache à chaque itération car les centroides changent
            $distanceCache = [];
            
            // Assigner chaque point au centroide le plus proche
            for ($i = 0; $i < $numPoints; $i++) {
                $minDistance = PHP_FLOAT_MAX;
                $closestCluster = 0;
                
                $pointStr = implode(',', $points[$i]);
                
                for ($j = 0; $j < $k; $j++) {
                    if (!isset($centroids[$j])) continue;
                    
                    $centroidStr = implode(',', $centroids[$j]);
                    $cacheKey = $pointStr . '|' . $centroidStr;
                    
                    if (!isset($distanceCache[$cacheKey])) {
                        $distanceCache[$cacheKey] = $this->errorCalculator->distanceDeltaCie($points[$i], $centroids[$j]);
                    }
                    
                    $distance = $distanceCache[$cacheKey];
                    
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $closestCluster = $j;
                    }
                }
                
                // Vérifier si le point change de cluster
                if ($clusters[$i] != $closestCluster) {
                    $clusters[$i] = $closestCluster;
                    $changesMade = true;
                }
            }
            
            // Recalculer les centroides
            $newCentroids = array_fill(0, $k, [0, 0, 0]);
            $clusterSizes = array_fill(0, $k, 0);
            $clusterWeights = array_fill(0, $k, 0);
            
            for ($i = 0; $i < $numPoints; $i++) {
                $c = $clusters[$i];
                $newCentroids[$c][0] += $points[$i][0] * $weights[$i];
                $newCentroids[$c][1] += $points[$i][1] * $weights[$i];
                $newCentroids[$c][2] += $points[$i][2] * $weights[$i];
                $clusterWeights[$c] += $weights[$i];
                $clusterSizes[$c]++;
            }
            
            // Normaliser les centroides
            for ($j = 0; $j < $k; $j++) {
                if ($clusterWeights[$j] > 0) {
                    $newCentroids[$j][0] = round($newCentroids[$j][0] / $clusterWeights[$j]);
                    $newCentroids[$j][1] = round($newCentroids[$j][1] / $clusterWeights[$j]);
                    $newCentroids[$j][2] = round($newCentroids[$j][2] / $clusterWeights[$j]);
                } else if ($clusterSizes[$j] == 0) {
                    // Si un cluster est vide, choisir un nouveau centroide aléatoire
                    if (!empty($indices)) {
                        $idx = array_rand($indices);
                        $newCentroids[$j] = $points[$indices[$idx]];
                        unset($indices[$idx]);
                    } else {
                        // Pas de point disponible, conserver l'ancien centroide
                        $newCentroids[$j] = $centroids[$j];
                    }
                }
            }
            
            // Mettre à jour les centroides
            $centroids = $newCentroids;
        }
        
        // Retourner les centroides comme palette
        return $centroids;
    }
    
    /**
     * Génère une palette avec Imagick
     * 
     * @param string $imagePath Chemin de l'image source
     * @param int $numColors Nombre de couleurs à extraire
     * @return array Résultat avec la palette et l'image recoloriée
     */
    public function generateImagickPalette($imagePath, $numColors) {
        try {
            // Charger l'image originale
            $imagick = new Imagick($imagePath);

            // Convertir explicitement en LAB avant la quantification
            $imagick->transformImageColorspace(13); // 13 = LAB colorspace
            
            // Créer une copie pour la quantification
            $quantized = clone $imagick;
            
            // Appliquer la quantification sur la copie
            $quantized->quantizeImage(
                $numColors,
                13, // LAB colorspace
                0,
                false, // Pas de dithering
                false  // Pas de treedepth
            );
            
            // Génération de la palette
            $palette = clone $quantized;
            $palette->uniqueImageColors();
            $colors = $palette->getImageHistogram();
            
            return [
                'imagick' => $imagick,
                'quantized' => $quantized,
                'colors' => $colors,
                'success' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée une image représentant la palette de couleurs
     * 
     * @param array $palette Palette de couleurs
     * @param int $swatchSize Taille des échantillons de couleur
     * @return resource Image GD de la palette
     */
    public function createPaletteImage($palette, $swatchSize) {
        $numColors = count($palette);
        $width = $numColors * $swatchSize;
        $height = $swatchSize;
        
        $image = imagecreatetruecolor($width, $height);
        
        // Dessiner chaque couleur dans l'image
        for ($i = 0; $i < $numColors; $i++) {
            $color = $palette[$i];
            $colorId = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            imagefilledrectangle(
                $image, 
                $i * $swatchSize, 0, 
                ($i + 1) * $swatchSize - 1, $height - 1, 
                $colorId
            );
        }
        
        return $image;
    }
    
    /**
     * Crée une image de palette à partir de couleurs Imagick
     * 
     * @param array $colors Couleurs Imagick
     * @param int $numColors Nombre de couleurs
     * @param int $swatchSize Taille des échantillons
     * @return Imagick Image de la palette
     */
    public function createImagickPaletteImage($colors, $numColors, $swatchSize) {
        // Vérification du nombre de couleurs
        $colorCount = count($colors);
        
        // Création de l'image palette
        $paletteImage = new Imagick();
        $paletteWidth = min($colorCount, $numColors) * $swatchSize;
        $paletteImage->newImage($paletteWidth, $swatchSize, new ImagickPixel('transparent'));
        
        $draw = new ImagickDraw();
        $x = 0;
        foreach ($colors as $color) {
            $draw->setFillColor($color);
            $draw->rectangle($x, 0, $x + $swatchSize, $swatchSize);
            $x += $swatchSize;
            
            // Limiter à nbCouleurs rectangles
            if ($x >= $paletteWidth) break;
        }
        $paletteImage->drawImage($draw);
        
        return $paletteImage;
    }
} 
