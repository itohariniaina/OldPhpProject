<?php

namespace App\Models;
use App\Models\ErrorCalculator;

class ImageRecolorer
{
    private $errorCalculator;
    public function __construct() {
        $this->errorCalculator = new ErrorCalculator();
        $this->errorCalculator->initLabTable();
        $this->setCacheSize(1000);
    }
    /**
     * Cache pour les couleurs proches
     * @var array
     */
    private $colorCache = [];
    
    /**
     * Cache pour les couleurs allouées
     * @var array
     */
    private $allocatedColors = [];
    
    /**
     * Nombre maximum d'entrées dans le cache
     * @var int
     */
    private $maxCacheSize = 1000;
    
    
    /**
     * Recolorie une image en utilisant la palette spécifiée
     * 
     * @param \GdImage $sourceImage L'image source à recolorier
     * @param array $palette La palette de couleurs à utiliser
     * @return \GdImage L'image recoloriée
     */
    public function recolorImage($sourceImage, $palette)
    {
        $this->manageCacheSize();
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        
        $recoloredImage = imagecreatetruecolor($width, $height);
        
        // Pré-allocation des couleurs pour des performances améliorées
        $this->preAllocateColors($recoloredImage, $palette);
        
        // Optimisation: Traiter par blocs de lignes
        $blockSize = 16;
        
        for ($blockY = 0; $blockY < $height; $blockY += $blockSize) {
            $endY = min($blockY + $blockSize, $height);
            
            for ($y = $blockY; $y < $endY; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($sourceImage, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    
                    $closestColor = $this->findClosestColor([$r, $g, $b], $palette);
                    $colorKey = "{$closestColor[0]},{$closestColor[1]},{$closestColor[2]}";
                    
                    // Vérifier si la couleur existe dans allocatedColors
                    if (!isset($this->allocatedColors[$colorKey])) {
                        $this->allocatedColors[$colorKey] = imagecolorallocate(
                            $recoloredImage, 
                            $closestColor[0], 
                            $closestColor[1], 
                            $closestColor[2]
                        );
                    }
                    
                    // Utiliser la couleur allouée
                    imagesetpixel($recoloredImage, $x, $y, $this->allocatedColors[$colorKey]);
                }
            }
        }
        
        return $recoloredImage;
    }
    
    /**
     * Pré-alloue toutes les couleurs de la palette pour l'image
     * 
     * @param \GdImage $image L'image pour laquelle allouer les couleurs
     * @param array $palette La palette de couleurs
     */
    private function preAllocateColors($image, $palette)
    {
        $this->allocatedColors = [];
        
        foreach ($palette as $color) {
            $r = $color[0];
            $g = $color[1];
            $b = $color[2];
            $colorKey = "$r,$g,$b";
            $this->allocatedColors[$colorKey] = imagecolorallocate($image, $r, $g, $b);
        }
    }
    
    /**
     * Trouve la couleur la plus proche dans la palette pour une couleur donnée
     * Utilise un système de cache pour éviter les calculs redondants
     * 
     * @param array $color La couleur à rechercher [r, g, b]
     * @param array $palette La palette de couleurs
     * @return array La couleur la plus proche [r, g, b]
     */
    public function findClosestColor($color, $palette)
    {
        // Clé de cache pour cette couleur
        $colorKey = implode(',', $color);
        
        // Vérifier si on a déjà calculé cette couleur
        if (isset($this->colorCache[$colorKey])) {
            return $this->colorCache[$colorKey];
        }
        
        // Si plus de maxCacheSize entrées dans le cache, vider la moitié la plus ancienne
        if (count($this->colorCache) > $this->maxCacheSize) {
            $this->colorCache = array_slice($this->colorCache, (int)($this->maxCacheSize / 2), null, true);
        }
        
        $minDistance = PHP_FLOAT_MAX;
        $closestColor = null;
        
        foreach ($palette as $paletteColor) {
            $distance = $this->calculateColorDistance($color, $paletteColor);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestColor = $paletteColor;
            }
        }
        
        // Stocker dans le cache pour réutilisation
        $this->colorCache[$colorKey] = $closestColor;
        return $closestColor;
    }
    
    /**
     * Calcule la distance perceptuelle entre deux couleurs RGB
     * Utilise l'algorithme Delta E CIE94 par défaut
     * 
     * @param array $color1 Première couleur [r, g, b]
     * @param array $color2 Deuxième couleur [r, g, b]
     * @return float La distance entre les deux couleurs
     */
    private function calculateColorDistance($color1, $color2)
    {
        
        // Optimization: Si les couleurs sont identiques, pas besoin de calculer
        if ($color1[0] == $color2[0] && $color1[1] == $color2[1] && $color1[2] == $color2[2]) {
            return 0;
        }
        $lab1 = $this->errorCalculator->getRgbToLabCached($color1);
        $lab2 = $this->errorCalculator->getRgbToLabCached($color2);
        // Utiliser la distance Delta E CIE94 qui est plus précise perceptuellement
        $erreur = $this->errorCalculator->distanceDeltaCie($lab1, $lab2);
        return $erreur;
    }
    
    
    /**
     * Méthode simplifiée pour calculer la distance euclidienne entre deux couleurs
     * 
     * @param array $color1 Première couleur [r, g, b]
     * @param array $color2 Deuxième couleur [r, g, b]
     * @return float La distance euclidienne
     */
    private function calculateEuclideanDistance($color1, $color2)
    {
        return sqrt(
            pow($color1[0] - $color2[0], 2) + 
            pow($color1[1] - $color2[1], 2) + 
            pow($color1[2] - $color2[2], 2)
        );
    }
    
    /**
     * Modifie le nombre maximum d'entrées dans le cache
     * 
     * @param int $size Nouvelle taille maximale du cache
     * @return ImageRecolorer Instance pour chaînage
     */
    public function setCacheSize($size)
    {
        $this->maxCacheSize = max(100, (int)$size);
        return $this;
    }
    
    /**
     * Vide le cache de couleurs
     * 
     * @return ImageRecolorer Instance pour chaînage
     */
    public function clearCache()
    {
        $this->colorCache = [];
        return $this;
    }

    /**
     * Gère la taille du cache de couleurs pour éviter une utilisation excessive de la mémoire
     * 
     * @return void
     */
    private function manageCacheSize()
    {
        if (count($this->colorCache) > $this->maxCacheSize) {
            $this->clearCache();
        }
    }
} 
