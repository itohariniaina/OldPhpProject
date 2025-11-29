<?php
namespace App\Models;

class ColorExtractor {
    /**
     * Extrait les couleurs d'une image GD
     * 
     * @param resource $image L'image GD
     * @return array Tableau des couleurs avec leur fréquence
     */
    public function extractColors($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $colors = [];
        
        // Facteur d'échantillonnage (analyser 1 pixel sur N)
        // Plus l'image est grande, plus on échantillonne
        $factor = max(1, floor(sqrt($width * $height) / 100));
        
        // Parcourir un sous-ensemble de pixels
        for ($y = 0; $y < $height; $y += $factor) {
            for ($x = 0; $x < $width; $x += $factor) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Réduire la précision des couleurs pour regrouper
                list($r, $g, $b) = $this->reduceColorPrecision($r, $g, $b);
                
                $color = [$r, $g, $b];
                $colorStr = implode(',', $color);
                
                if (!isset($colors[$colorStr])) {
                    $colors[$colorStr] = [
                        'rgb' => $color,
                        'count' => 0
                    ];
                }
                
                $colors[$colorStr]['count']++;
            }
        }
        
        return $colors;
    }
    
    /**
     * Réduit la précision des couleurs pour regrouper les nuances similaires
     * 
     * @param int $r composante rouge
     * @param int $g composante verte
     * @param int $b composante bleue
     * @param int $levels nombre de niveaux de précision
     * @return array Couleur réduite [r, g, b]
     */
    private function reduceColorPrecision($r, $g, $b, $levels = 8) {
        $factor = 256 / $levels;
        return [
            floor($r / $factor) * $factor,
            floor($g / $factor) * $factor,
            floor($b / $factor) * $factor
        ];
    }
    
    /**
     * Prépare une image pour l'analyse en la redimensionnant
     * 
     * @param resource $image L'image source
     * @param int $maxSize Taille maximale
     * @return resource Image redimensionnée
     */
    public function prepareImageForAnalysis($image, $maxSize) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        if ($width <= $maxSize && $height <= $maxSize) {
            return $image;
        }
        
        if ($width > $height) {
            $newWidth = $maxSize;
            $newHeight = round($height * ($maxSize / $width));
        } else {
            $newHeight = $maxSize;
            $newWidth = round($width * ($maxSize / $height));
        }
        
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        return $resizedImage;
    }
} 
