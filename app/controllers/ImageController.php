<?php
namespace App\Controllers;

use App\Models\ImageProcessor;

class ImageController {
    private $imageProcessor;
    private $uploadDir;
    private $outputDir;
    
    /**
     * Constructeur - initialise les propriétés
     */
    public function __construct() {
        $this->imageProcessor = new ImageProcessor();
        $this->uploadDir = BASE_PATH . '/uploads';
        $this->outputDir = BASE_PATH . '/output';
        
        // Créer les répertoires s'ils n'existent pas
        $this->ensureDirectoriesExist();
    }
    
    /**
     * Affiche la page d'accueil
     */
    public function index() {
        require_once BASE_PATH . '/app/views/index.php';
    }
    
    /**
     * Traite l'image téléchargée
     */
    public function processImage() {
        // Vérifier si la requête est bien de type POST
        if ($_SERVER["REQUEST_METHOD"] != "POST") {
            $this->redirect('home');
            return;
        }
        
        // Vérifier si un fichier a été téléchargé
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->handleUploadError($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
            return;
        }
        
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            $this->setError("Type de fichier non autorisé. Seuls les formats JPEG, PNG et GIF sont acceptés.");
            $this->redirect('home');
            return;
        }
        
        // Déplacer le fichier téléchargé
        $sourceFile = $this->uploadDir . '/source.jpg';
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $sourceFile)) {
            $this->setError("Impossible de déplacer le fichier téléchargé.");
            $this->redirect('home');
            return;
        }
        
        // Récupérer le nombre de couleurs
        $nbCouleurs = isset($_POST['nbCouleurs']) ? (int)$_POST['nbCouleurs'] : 8;
        if ($nbCouleurs < 2) $nbCouleurs = 2;
        if ($nbCouleurs > 256) $nbCouleurs = 256;
        
        try {
            // Traiter l'image avec les différentes méthodes
            app_log("Début du traitement - Image reçue");
            $results = $this->imageProcessor->processImage($sourceFile, $nbCouleurs);
            // Rediriger vers la page de résultats
            $params = http_build_query([
                'processed' => 'true',
                'nbCouleurs' => $nbCouleurs,
                'erreur_naive' => $results['erreur_naive'],
                'erreur_kmeans' => $results['erreur_kmeans'],
                'erreur_imagick' => $results['erreur_imagick']
            ]);
            app_log("Traitement terminé avec succès");
            $this->redirect('results?' . $params);
            
        } catch (\Exception $e) {
            app_log("ERREUR: Échec du traitement - " . $e->getMessage());
            $this->setError("Erreur lors du traitement : " . $e->getMessage());
            $this->redirect('home');
        }
    }
    
    /**
     * Affiche les résultats du traitement
     */
    public function showResults() {
        if (!defined('APP_PATH')) {
            define('APP_PATH', BASE_PATH . '/app');
        }
        require_once APP_PATH . '/views/results.php';
    }
    
    /**
     * Gère les erreurs de téléchargement
     */
    private function handleUploadError($errorCode) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "Le fichier téléchargé dépasse la limite définie dans php.ini.",
            UPLOAD_ERR_FORM_SIZE => "Le fichier téléchargé dépasse la limite définie dans le formulaire HTML.",
            UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement téléchargé.",
            UPLOAD_ERR_NO_FILE => "Aucun fichier n'a été téléchargé.",
            UPLOAD_ERR_NO_TMP_DIR => "Le dossier temporaire est manquant.",
            UPLOAD_ERR_CANT_WRITE => "Impossible d'écrire le fichier sur le disque.",
            UPLOAD_ERR_EXTENSION => "Une extension PHP a arrêté le téléchargement du fichier."
        ];
        
        $errorMessage = $errorMessages[$errorCode] ?? "Erreur inconnue lors du téléchargement.";
        $this->setError($errorMessage);
        $this->redirect('home');
    }
    
    /**
     * Définit un message d'erreur en session
     */
    private function setError($message) {
        $_SESSION['error'] = $message;
    }
    
    /**
     * S'assure que les répertoires nécessaires existent
     */
    private function ensureDirectoriesExist() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Redirige vers une route
     */
    public function redirect($route, $params = []) {
        $url = '/ProjetPHP/public/' . $route;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        header("Location: " . $url);
        exit;
    }
} 
