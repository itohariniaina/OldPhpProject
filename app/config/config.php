<?php
/**
 * Configuration générale de l'application
 */
class Config {
    // Chemins de base
    const BASE_PATH = __DIR__ . '/ProjetPHP';
    const APP_PATH = self::BASE_PATH . 'app/';
    const UPLOADS_DIR = self::BASE_PATH . 'uploads/';
    const OUTPUT_DIR = self::BASE_PATH . 'output/';
    const BASE_URL = '/ProjetPHP/public';
    
    // Limite de mémoire et temps d'exécution
    const MEMORY_LIMIT = '256M';
    const MAX_EXECUTION_TIME = 300; // 5 minutes
    
    // Configuration du traitement d'image
    const MAX_IMAGE_SIZE = 800; // Taille maximale pour le traitement
    const ANALYSIS_SIZE = 400; // Taille pour l'analyse des couleurs
    const PALETTE_SWATCH_SIZE = 50; // Taille des échantillons de couleur
    const MAX_KMEANS_ITERATIONS = 5; // Nombre d'itérations pour K-means
    
    // Types de fichiers acceptés
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    
    /**
     * Initialise les paramètres PHP
     */
    public static function init() {
        // Augmenter la limite de mémoire pour traiter de grandes images
        ini_set('memory_limit', self::MEMORY_LIMIT);
        ini_set('max_execution_time', self::MAX_EXECUTION_TIME);
        
        // Créer les répertoires s'ils n'existent pas
        if (!file_exists(self::UPLOADS_DIR)) {
            mkdir(self::UPLOADS_DIR, 0777, true);
        }
        if (!file_exists(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0777, true);
        }
    }
    
    /**
     * Vérifie les extensions PHP requises
     */
    public static function checkRequirements() {
        if (!extension_loaded('gd')) {
            die("L'extension GD est requise pour ce script");
        }
        
        if (!extension_loaded('imagick')) {
            die("L'extension Imagick est requise pour ce script");
        }
    }
} 
