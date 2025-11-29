<?php
/**
 * Point d'entrée principal de l'application
 * Gère le routage et l'initialisation
 */

// Définir le chemin racine de l'application
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('APP_URL', '/ProjetPHP/public');
define('LOG_FILE', BASE_PATH . '/logs/app.log');


function app_log($message) {
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

// Charger la configuration
require_once BASE_PATH . '/app/config/config.php';

// Démarrer la session
session_start();

// Fonction d'autoloading des classes
spl_autoload_register(function ($className) {
    // Convertir le namespace en chemin de fichier
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    if (strpos($classPath, 'App') === 0) {
        $classPath = str_replace('App', 'app', $classPath);
    }
    
    $filePath = BASE_PATH . DIRECTORY_SEPARATOR . $classPath . '.php';
    
    if (file_exists($filePath)) {
        require_once $filePath;
    }
});


$uploadDir = BASE_PATH . '/uploads';
$outputDir = BASE_PATH . '/output';

// Récupérer l'URI de la requête
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/ProjetPHP/public/';  

// Supprimer les paramètres de requête s'il y en a
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}

// Nettoyer l'URI
$requestUri = rtrim(substr($requestUri, strlen($basePath)), '/');
if (empty($requestUri)) {
    $requestUri = 'home';
}

// Routes simples
$routes = [
    'home' => ['controller' => 'app\controllers\ImageController', 'action' => 'index'],
    'process-image' => ['controller' => 'app\controllers\ImageController', 'action' => 'processImage'],
    'results' => ['controller' => 'app\controllers\ImageController', 'action' => 'showResults'],
];

// Vérifier si la route existe
if (isset($routes[$requestUri])) {
    $controllerName = $routes[$requestUri]['controller'];
    $actionName = $routes[$requestUri]['action'];
    
    // Instancier le contrôleur
    $controller = new $controllerName();
    
    // Appeler l'action
    $controller->$actionName();
} else {
    // Page 404
    header("HTTP/1.0 404 Not Found");
    echo '<h1>Page non trouvée</h1>';
    echo '<p>La page que vous recherchez n\'existe pas.</p>';
    echo '<p><a href="' . $basePath . '">Retour à l\'accueil</a></p>';
}