<?php
namespace App\Models;

use App\Models\ColorExtractor;
use App\Models\ColorPalette;
use App\Models\ImageRecolorer;
use App\Models\ErrorCalculator;

class ImageProcessor {
    private $colorExtractor;
    private $colorPalette;
    private $imageRecolorer;
    private $errorCalculator;
    private $outputDir;
    
    public function __construct() {
        $this->colorExtractor = new ColorExtractor();
        $this->colorPalette = new ColorPalette();
        $this->errorCalculator = new ErrorCalculator();
        $this->imageRecolorer = new ImageRecolorer();
        $this->outputDir = BASE_PATH . '/output';
    }
    
    /**
     * Traite une image avec différentes méthodes de quantification
     * 
     * @param string $sourceFile Chemin vers le fichier source
     * @param int $nbCouleurs Nombre de couleurs pour la palette
     * @return array Résultats des traitements
     */
    public function processImage($sourceFile, $nbCouleurs) {
        // Augmenter la limite de mémoire pour traiter de grandes images
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 300); // 5 minutes
        $startTime = microtime(true);
        // Charger l'image source
        $image = $this->chargerImage($sourceFile);
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        
         // Configuration optimale du cache selon la taille de l'image
        $imageInfo = getimagesize($sourceFile);
        $pixelCount = $imageInfo[0] * $imageInfo[1];
        $optimalCacheSize = min(5000, max(1000, (int)($pixelCount / 100)));
    
        $this->imageRecolorer->setCacheSize($optimalCacheSize);
        // Redimensionner l'image si nécessaire (pour améliorer les performances)
        $image = $this->redimensionnerSiNecessaire($image, $largeur, $hauteur);
        $tailleVignettePalette = 50; // Taille en pixels de chaque couleur dans la palette
        
        // Créer une version plus petite pour l'analyse des couleurs (optimisation)
        $imageAnalyse = $this->creerImageAnalyse($image);
        try {
        // Extraction des couleurs
        $couleurs = $this->colorExtractor->extractColors($imageAnalyse);
        
        // Traitement avec méthode naïve
        $resultatsNaive = $this->traiterMethodeNaive($image, $couleurs, $nbCouleurs, $tailleVignettePalette);
        app_log("Méthode naïve terminée");
        // Traitement avec méthode K-means
        $resultatsKmeans = $this->traiterMethodeKmeans($image, $couleurs, $nbCouleurs, $tailleVignettePalette);
        $endTime = microtime(true);
        app_log("Méthode k-means terminée ");
        app_log("Méthode imagick terminée ");
        app_log("Temps de traitement des méthodes naïves et k-means : " . ($endTime - $startTime) . " secondes");
        } catch (Exception $e){
            app_log("ERREUR: Échec de quantification - " . $e->getMessage());
            throw $e;
        }
        // Traitement avec méthode Imagick
        $resultatsImagick = $this->traiterMethodeImagick($sourceFile, $nbCouleurs, $tailleVignettePalette);
        
        // Libérer la mémoire
        imagedestroy($image);
        if (isset($imageAnalyse)) {
            imagedestroy($imageAnalyse);
        }
        
        // Retourner les résultats combinés
        return [
            'erreur_naive' => $resultatsNaive['erreur'],
            'erreur_kmeans' => $resultatsKmeans['erreur'],
            'erreur_imagick' => $resultatsImagick['erreur']
        ];
    }
    
    /**
     * Charge une image à partir d'un fichier
     */
    private function chargerImage($cheminImage) {
        $info = getimagesize($cheminImage);
        $type = $info[2];
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($cheminImage);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($cheminImage);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($cheminImage);
            default:
                throw new \Exception("Format d'image non supporté");
        }
    }
    
    /**
     * Redimensionne l'image si nécessaire
     */
    private function redimensionnerSiNecessaire($image, $largeur, $hauteur) {
        $maxSize = 800;
        
        if ($largeur > $maxSize || $hauteur > $maxSize) {
            if ($largeur > $hauteur) {
                $newLargeur = $maxSize;
                $newHauteur = round($hauteur * ($maxSize / $largeur));
            } else {
                $newHauteur = $maxSize;
                $newLargeur = round($largeur * ($maxSize / $hauteur));
            }
            
            $imageRedim = imagecreatetruecolor($newLargeur, $newHauteur);
            imagecopyresampled($imageRedim, $image, 0, 0, 0, 0, $newLargeur, $newHauteur, $largeur, $hauteur);
            imagedestroy($image);
            return $imageRedim;
        }
        
        return $image;
    }
    
    /**
     * Crée une image d'analyse plus petite pour l'extraction des couleurs
     */
    private function creerImageAnalyse($image) {
        $maxSizeAnalysis = 400;
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        
        if ($largeur > $maxSizeAnalysis || $hauteur > $maxSizeAnalysis) {
            if ($largeur > $hauteur) {
                $analyseWidth = $maxSizeAnalysis;
                $analyseHeight = round($hauteur * ($maxSizeAnalysis / $largeur));
            } else {
                $analyseHeight = $maxSizeAnalysis;
                $analyseWidth = round($largeur * ($maxSizeAnalysis / $hauteur));
            }
            
            $imageAnalyse = imagecreatetruecolor($analyseWidth, $analyseHeight);
            imagecopyresampled($imageAnalyse, $image, 0, 0, 0, 0, $analyseWidth, $analyseHeight, $largeur, $hauteur);
            return $imageAnalyse;
        }
        
        return $image;
    }
    
    /**
     * Traite l'image avec la méthode naïve
     */
    private function traiterMethodeNaive($image, $couleurs, $nbCouleurs, $tailleVignette) {
        // Générer la palette naïve
        $paletteNaive = $this->colorPalette->generateNaivePalette($couleurs, $nbCouleurs);
        $imagePaletteNaive = $this->colorPalette->createPaletteImage($paletteNaive, $tailleVignette);
        
        // Recolorier l'image
        $imageRecoloriee = $this->imageRecolorer->recolorImage($image, $paletteNaive);
        
        // Calculer l'erreur
        $erreur = $this->errorCalculator->calculerErreurCIEDE2000($image, $imageRecoloriee);
        
        // Sauvegarder les résultats
        imagejpeg($imagePaletteNaive, "{$this->outputDir}/palette_naive.jpg", 90);
        imagejpeg($imageRecoloriee, "{$this->outputDir}/recoloriee_naive.jpg", 90);
        
        // Libérer la mémoire
        imagedestroy($imagePaletteNaive);
        imagedestroy($imageRecoloriee);
        
        return ['erreur' => $erreur];
    }
    
    /**
     * Traite l'image avec la méthode K-means
     */
    private function traiterMethodeKmeans($image, $couleurs, $nbCouleurs, $tailleVignette) {
        // Générer la palette K-means
        $paletteKmeans = $this->colorPalette->generateKmeansPalette($couleurs, $nbCouleurs);
        $imagePaletteKmeans = $this->colorPalette->createPaletteImage($paletteKmeans, $tailleVignette);
        
        // Recolorier l'image
        $imageRecoloriee = $this->imageRecolorer->recolorImage($image, $paletteKmeans);
        
        // Calculer l'erreur
        $erreur = $this->errorCalculator->calculerErreurCIEDE2000($image, $imageRecoloriee);
        
        // Sauvegarder les résultats
        imagejpeg($imagePaletteKmeans, "{$this->outputDir}/palette_kmeans.jpg", 90);
        imagejpeg($imageRecoloriee, "{$this->outputDir}/recoloriee_kmeans.jpg", 90);
        
        // Libérer la mémoire
        imagedestroy($imagePaletteKmeans);
        imagedestroy($imageRecoloriee);
        
        return ['erreur' => $erreur];
    }
    
    /**
     * Traite l'image avec la méthode Imagick
     */
    private function traiterMethodeImagick($sourceFile, $nbCouleurs, $tailleVignette) {
        try {
            // Vérification des extensions nécessaires
            if (!extension_loaded('imagick')) {
                throw new \Exception("L'extension Imagick est requise pour cette méthode");
            }
            
            // Charger l'image originale
            $imagick = new \Imagick($sourceFile);
            
            // Convertir explicitement en LAB avant la quantification
            $imagick->transformImageColorspace(13); // 13 = LAB
            
            // Créer une copie pour la quantification
            $quantized = clone $imagick;
            
            // Appliquer la quantification sur la copie
            $quantized->quantizeImage(
                $nbCouleurs,
                13, // Colorspace LAB
                0,  // Dither 0 = pas de dithering
                false, // Pas de dithering
                false  // Pas de treedepth
            );
            
            // Génération de la palette
            $palette = clone $quantized;
            $palette->uniqueImageColors();
            $colors = $palette->getImageHistogram();
            
            // Vérification du nombre de couleurs
            $colorCount = count($colors);
            
            // Création de l'image palette
            $paletteImage = new \Imagick();
            $paletteWidth = min($colorCount, $nbCouleurs) * $tailleVignette;
            $paletteImage->newImage($paletteWidth, $tailleVignette, new \ImagickPixel('transparent'));
            
            $draw = new \ImagickDraw();
            $x = 0;
            foreach ($colors as $color) {
                $draw->setFillColor($color);
                $draw->rectangle($x, 0, $x + $tailleVignette, $tailleVignette);
                $x += $tailleVignette;
                
                // Limiter à nbCouleurs rectangles
                if ($x >= $paletteWidth) break;
            }
            $paletteImage->drawImage($draw);
            
            // Calcul de l'erreur entre l'image originale et l'image quantifiée
            $errorResult = $imagick->compareImages($quantized, \Imagick::METRIC_ROOTMEANSQUAREDERROR);
            $erreur = round($errorResult[1], 6);
            
            // Sauvegarde des résultats
            $quantized->writeImage("{$this->outputDir}/recoloriee_imagick.jpg");
            $paletteImage->writeImage("{$this->outputDir}/palette_imagick.jpg");
            
            return ['erreur' => $erreur];
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un message
            file_put_contents("{$this->outputDir}/erreur_imagick.txt", "Erreur : " . $e->getMessage());
            return ['erreur' => "Erreur: " . $e->getMessage()];
        }
    }
} 
