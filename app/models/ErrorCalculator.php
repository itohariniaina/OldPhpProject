<?php

namespace App\Models;

class ErrorCalculator
{
    /**
     * Calcule l'erreur moyenne entre deux images
     * 
     * @param resource $imageOriginale Image GD originale
     * @param resource $imageRecoloriee Image GD recolorée
     * @param int $sampleRate Taux d'échantillonnage (1 pixel sur $sampleRate sera analysé)
     * @return float Erreur moyenne normalisée (entre 0 et 1)
     */
    public function calculerErreurMoyenne($imageOriginale, $imageRecoloriee, $sampleRate = null)
    {
        $largeur = imagesx($imageOriginale);
        $hauteur = imagesy($imageOriginale);
        
        // Déterminer le taux d'échantillonnage si non spécifié
        if ($sampleRate === null) {
            $sampleRate = max(1, floor(sqrt($largeur * $hauteur) / 50));
        }
        
        $sommeErreursCarrees = 0;
        $compteur = 0;
        
        // Parcourir l'image en échantillonnant
        for ($y = 0; $y < $hauteur; $y += $sampleRate) {
            for ($x = 0; $x < $largeur; $x += $sampleRate) {
                $rgb1 = imagecolorat($imageOriginale, $x, $y);
                $rgb2 = imagecolorat($imageRecoloriee, $x, $y);

                // Extraire les composantes RGB
                $r1 = ($rgb1 >> 16) & 0xFF;
                $g1 = ($rgb1 >> 8) & 0xFF;
                $b1 = $rgb1 & 0xFF;

                $r2 = ($rgb2 >> 16) & 0xFF;
                $g2 = ($rgb2 >> 8) & 0xFF;
                $b2 = $rgb2 & 0xFF;

                // Calculer l'erreur quadratique pour ce pixel
                $erreurPixelCarree = pow($r1 - $r2, 2) + pow($g1 - $g2, 2) + pow($b1 - $b2, 2);
                $sommeErreursCarrees += $erreurPixelCarree;
                $compteur++;
            }
        }

        // Calculer la racine carrée de l'erreur quadratique moyenne (RMSE)
        $rmseBrute = sqrt($sommeErreursCarrees / $compteur);
        
        // Normaliser entre 0 et 1 (où 0 = identique, 1 = erreur maximale)
        $distanceMaximale = 255 * sqrt(3);  // Distance maximale possible en RGB (255,255,255)
        $rmseNormalisee = $rmseBrute / $distanceMaximale;

        return $rmseNormalisee;
    }
    
    /**
     * Calcule l'erreur CIEDE2000 entre deux images (plus précise perceptuellement)
     * 
     * @param resource $imageOriginale Image GD originale
     * @param resource $imageRecoloriee Image GD recolorée
     * @param int $sampleRate Taux d'échantillonnage
     * @return float Erreur moyenne CIEDE2000
     */
    public function calculerErreurCIEDE2000($imageOriginale, $imageRecoloriee, $sampleRate = null)
    {
        $largeur = imagesx($imageOriginale);
        $hauteur = imagesy($imageOriginale);
        
        // Déterminer le taux d'échantillonnage si non spécifié
        if ($sampleRate === null) {
            $sampleRate = max(1, floor(sqrt($largeur * $hauteur) / 30));
        }
        
        $sommeErreurs = 0;
        $compteur = 0;
        
        // Parcourir l'image en échantillonnant
        for ($y = 0; $y < $hauteur; $y += $sampleRate) {
            for ($x = 0; $x < $largeur; $x += $sampleRate) {
                $rgb1 = imagecolorat($imageOriginale, $x, $y);
                $rgb2 = imagecolorat($imageRecoloriee, $x, $y);

                // Extraire les composantes RGB
                $couleur1 = [
                    ($rgb1 >> 16) & 0xFF,
                    ($rgb1 >> 8) & 0xFF,
                    $rgb1 & 0xFF
                ];
                
                $couleur2 = [
                    ($rgb2 >> 16) & 0xFF,
                    ($rgb2 >> 8) & 0xFF,
                    $rgb2 & 0xFF
                ];

                // Calculer la distance Delta-E CIE
                $distance = $this->distanceDeltaCie($couleur1, $couleur2);
                $sommeErreurs += $distance;
                $compteur++;
            }
        }

        // Calculer l'erreur moyenne
        return $sommeErreurs / $compteur;
    }
    
    /**
     * Calcule la distance delta CIE entre deux couleurs
     * 
     * @param array $color1 Première couleur [r, g, b]
     * @param array $color2 Deuxième couleur [r, g, b]
     * @return float Distance entre les couleurs
     */
    public function distanceDeltaCie($color1, $color2) {
        // Optimisation : Si les couleurs sont identiques, pas besoin de calculer
        if ($color1[0] == $color2[0] && $color1[1] == $color2[1] && $color1[2] == $color2[2]) {
            return 0;
        }
        
        // Utiliser le cache pour les conversions Lab
        $lab1 = $this->getRgbToLabCached($color1);
        $lab2 = $this->getRgbToLabCached($color2);
        
        // Différences Lab
        $dL = $lab1[0] - $lab2[0];
        $da = $lab1[1] - $lab2[1];
        $db = $lab1[2] - $lab2[2];
        
        // Calculs intermédiaires
        $c1 = sqrt(pow($lab1[1], 2) + pow($lab1[2], 2));
        $c2 = sqrt(pow($lab2[1], 2) + pow($lab2[2], 2));
        $dC = $c1 - $c2;
        
        // Optimisation: Calcul simplifié de dH
        $dH_squared = $da*$da + $db*$db - $dC*$dC;
        // Éviter les erreurs d'arrondi négatives
        $dH = ($dH_squared > 0) ? sqrt($dH_squared) : 0;
        
        // Facteurs de pondération 
        $kL = 1.0;
        $k1 = 0.045;
        $k2 = 0.015;
        
        $kC = 1.0;
        $kH = 1.0;
        
        $sL = 1.0;
        $sC = 1.0 + $k1 * $c1;
        $sH = 1.0 + $k2 * $c1;
        
        // Calcul final
        return sqrt(
            pow($dL / ($kL * $sL), 2) +
            pow($dC / ($kC * $sC), 2) +
            pow($dH / ($kH * $sH), 2)
        );
    }
    
    /**
     * Cache pour les conversions RGB -> Lab
     */
    public function initLabTable() {
        if (empty($this->labCache)) {
            // Stocker une version plus grossière de la table (tous les 8 niveaux)
            for ($r = 0; $r < 256; $r += 8) {
                for ($g = 0; $g < 256; $g += 8) {
                    for ($b = 0; $b < 256; $b += 8) {
                        $key = ($r << 16) | ($g << 8) | $b;
                        $this->labCache[$key] = $this->rgb2lab([$r, $g, $b]);
                    }
                }
            }
        }
    }
    
    /**
     * Récupère la conversion RGB -> Lab avec cache
     * 
     * @param array $rgb Couleur RGB [r, g, b]
     * @return array Couleur Lab [l, a, b]
     */
    public function getRgbToLabCached($rgb) {
        if (empty($this->labCache)) {
            $this->initLabTable();
        }
        
        // Arrondir aux valeurs les plus proches dans notre table
        $r = round($rgb[0] / 8) * 8;
        $g = round($rgb[1] / 8) * 8;
        $b = round($rgb[2] / 8) * 8;
        
        // Limiter les valeurs entre 0-255
        $r = max(0, min(248, $r));
        $g = max(0, min(248, $g));
        $b = max(0, min(248, $b));
        
        $key = ($r << 16) | ($g << 8) | $b;
        
        if (isset($this->labCache[$key])) {
            return $this->labCache[$key];
        }
        
        // Si non trouvé dans le cache, calculer directement
        return $this->rgb2lab($rgb);
    }
    
    /**
     * Convertit RGB en XYZ
     * 
     * @param array $rgb Couleur RGB [r, g, b]
     * @return array Couleur XYZ [x, y, z]
     */
    public function rgb2xyz($rgb) {
        // Normalisation des valeurs RGB (0-1)
        $r = $rgb[0] / 255;
        $g = $rgb[1] / 255;
        $b = $rgb[2] / 255;
        
        // Appliquer la correction gamma
        $r = ($r > 0.04045) ? pow(($r + 0.055) / 1.055, 2.4) : $r / 12.92;
        $g = ($g > 0.04045) ? pow(($g + 0.055) / 1.055, 2.4) : $g / 12.92;
        $b = ($b > 0.04045) ? pow(($b + 0.055) / 1.055, 2.4) : $b / 12.92;
        
        // Conversion RGB -> XYZ selon la matrice de transformation standard
        $x = $r * 0.4124 + $g * 0.3576 + $b * 0.1805;
        $y = $r * 0.2126 + $g * 0.7152 + $b * 0.0722;
        $z = $r * 0.0193 + $g * 0.1192 + $b * 0.9505;
        
        return [$x, $y, $z];
    }
    
    /**
     * Convertit XYZ en Lab
     * 
     * @param array $xyz Couleur XYZ [x, y, z]
     * @return array Couleur Lab [l, a, b]
     */
    public function xyz2lab($xyz) {
        // Points de référence D65
        $xRef = 0.95047;
        $yRef = 1.00000;
        $zRef = 1.08883;
        
        $x = $xyz[0] / $xRef;
        $y = $xyz[1] / $yRef;
        $z = $xyz[2] / $zRef;
        
        $x = ($x > 0.008856) ? pow($x, 1/3) : (7.787 * $x) + (16/116);
        $y = ($y > 0.008856) ? pow($y, 1/3) : (7.787 * $y) + (16/116);
        $z = ($z > 0.008856) ? pow($z, 1/3) : (7.787 * $z) + (16/116);
        
        $l = (116 * $y) - 16;
        $a = 500 * ($x - $y);
        $b = 200 * ($y - $z);
        
        return [$l, $a, $b];
    }
    
    /**
     * Convertit RGB en Lab
     * 
     * @param array $rgb Couleur RGB [r, g, b]
     * @return array Couleur Lab [l, a, b]
     */
    public function rgb2lab($rgb) {
        $xyz = $this->rgb2xyz($rgb);
        return $this->xyz2lab($xyz);
    }
} 
