<?php

class Config
{
    private static $instance = null;
    private $config = [];

    /**
     * Pattern Singleton - Une seule instance de configuration
     */
    private function __construct()
    {
        $this->loadEnvFile();
        $this->loadConfig();
    }

    /**
     * Récupérer l'instance unique de Config
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ✅ NOUVEAU : Charger le fichier .env
     */
    private function loadEnvFile(): void
    {
        $envFile = __DIR__ . '/../.env';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                // Ignorer les commentaires
                if (strpos($line, '#') === 0) {
                    continue;
                }

                // Séparer clé=valeur
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Enlever les guillemets si présents
                    if (
                        (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                        (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)
                    ) {
                        $value = substr($value, 1, -1);
                    }

                    // Charger dans $_ENV et $_SERVER
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    putenv("$key=$value");
                }
            }

            error_log("Fichier .env chargé avec succès");
        } else {
            error_log("Fichier .env non trouvé dans : " . $envFile);
        }
    }

    /**
     * Charger la configuration depuis les variables ou fichier
     */
    private function loadConfig(): void
    {
        // Configuration de la base de données - ADAPTÉE À TON PROJET
        $this->config = [
            'database' => [
                'host' => $_ENV['DB_HOST'] ,
                'port' => $_ENV['DB_PORT'] ,
                'dbname' => $_ENV['DB_NAME'],
                'username' => $_ENV['DB_USER'] ,
                'password' => $_ENV['DB_PASS'] ,
                'charset' => 'utf8',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            ],
            'app' => [
                'name' => 'Bibliothèque Numérique',
                'version' => '2.0.0',
                'debug' => $_ENV['APP_DEBUG'] ?? false,
                'timezone' => 'Europe/Paris',
                'upload_path' => $_ENV['UPLOAD_PATH'] ?? 'assets/uploads/',
                'max_file_size' => 50 * 1024 * 1024, // 50MB
            ],
            'security' => [
                'session_name' => 'LIBRARY_SESSION',
                'session_lifetime' => 3600, // 1 heure
                'password_min_length' => 6,
                'allowed_file_types' => ['pdf', 'epub', 'jpg', 'jpeg', 'png', 'gif']
            ],
            // ✅ NOUVEAU : Configuration email
            'mail' => [
                'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
                'port' => $_ENV['MAIL_PORT'] ?? 587,
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@localhost',
                'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Bibliothèque Numérique'
            ]
        ];
    }

    /**
     * Récupérer une valeur de configuration
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Définir une valeur de configuration
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Récupérer toute la configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Empêcher le clonage
     */
    private function __clone()
    {
    }

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup(): void
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

class Database
{
    private static $instance = null;
    private $connection = null;
    private $config;

    private function __construct()
    {
        $this->config = Config::getInstance();
        $this->connect();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect(): void
    {
        try {
            $host = $this->config->get('database.host');
            $dbname = $this->config->get('database.dbname');
            $username = $this->config->get('database.username');
            $password = $this->config->get('database.password');

            // DSN identique à ton fichier actuel
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8";

            $this->connection = new PDO($dsn, $username, $password);

            // Attributs identiques à ton fichier actuel
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->exec("SET NAMES utf8");

            // Désactiver le mode strict GROUP BY si nécessaire
            $this->connection->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            if (config('app.debug', false)) {
                die("Erreur de connexion : " . $e->getMessage());
            } else {
                die("Erreur de connexion à la base de données.");
            }
        }
    }

    /**
     * Récupérer la connexion PDO
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Préparer une requête
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->getConnection()->prepare($sql);
    }

    /**
     * Exécuter une requête directe
     */
    public function query(string $sql)
    {
        return $this->getConnection()->query($sql);
    }

    /**
     * Exécuter une requête directe
     */
    public function exec(string $sql): int
    {
        return $this->getConnection()->exec($sql);
    }

    /**
     * Récupérer le dernier ID inséré
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Commencer une transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Valider une transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Annuler une transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }

    /**
     * Vérifier si une transaction est en cours
     */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * Fermer la connexion
     */
    public function close(): void
    {
        $this->connection = null;
    }

    /**
     * Empêcher le clonage
     */
    private function __clone()
    {
    }

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup(): void
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Fonction helper pour récupérer la connexion PDO
 */
function getDatabase(): PDO
{
    return Database::getInstance()->getConnection();
}

/**
 * Fonction helper pour récupérer la configuration
 */
function config(string $key, $default = null)
{
    return Config::getInstance()->get($key, $default);
}


function timeAgo($datetime) {
    if (empty($datetime)) {
        return 'Date inconnue';
    }
    
    $timestamp = strtotime($datetime);
    $current_time = time();
    $difference = $current_time - $timestamp;
    
    // Secondes
    if ($difference < 60) {
        return 'à l\'instant';
    }
    
    // Minutes
    if ($difference < 3600) {
        $minutes = floor($difference / 60);
        return 'il y a ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
    
    // Heures
    if ($difference < 86400) {
        $hours = floor($difference / 3600);
        return 'il y a ' . $hours . ' heure' . ($hours > 1 ? 's' : '');
    }
    
    // Jours
    if ($difference < 604800) {
        $days = floor($difference / 86400);
        return 'il y a ' . $days . ' jour' . ($days > 1 ? 's' : '');
    }
    
    // Semaines
    if ($difference < 2592000) {
        $weeks = floor($difference / 604800);
        return 'il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
    }
    
    // Mois
    if ($difference < 31536000) {
        $months = floor($difference / 2592000);
        return 'il y a ' . $months . ' mois';
    }
    
    // Années
    $years = floor($difference / 31536000);
    return 'il y a ' . $years . ' an' . ($years > 1 ? 's' : '');
}





















function getInitials($user): string {
    // Si c'est un objet User
    if (is_object($user)) {
        $firstName = $user->first_name ?? $user->getFirstName() ?? '';
        $lastName = $user->last_name ?? $user->getLastName() ?? '';
        $username = $user->username ?? $user->getUsername() ?? 'U';
    } 
    // Si c'est un tableau
    else if (is_array($user)) {
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        $username = $user['username'] ?? 'U';
    }
    else {
        return 'U';
    }
    
    // Si on a un prénom, prendre les 2 premières lettres
    if (!empty($firstName)) {
        return strtoupper(mb_substr($firstName, 0, 2, 'UTF-8'));
    }
    
    // Sinon, prendre la première lettre du prénom et du nom
    if (!empty($firstName) && !empty($lastName)) {
        return strtoupper(mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'));
    }
    
    // Sinon, prendre les 2 premières lettres du username
    if (!empty($username)) {
        return strtoupper(mb_substr($username, 0, 2, 'UTF-8'));
    }
    
    return 'U';
}

/**
 * Génère une couleur d'avatar basée sur le nom (pour avoir des couleurs cohérentes)
 * @param string $name Nom de l'utilisateur
 * @return string Code couleur hexadécimal
 */
function getAvatarColor(string $name): string {
    $colors = [
        '#3b82f6', // Bleu
        '#8b5cf6', // Violet
        '#ec4899', // Rose
        '#f59e0b', // Orange
        '#10b981', // Vert
        '#06b6d4', // Cyan
        '#6366f1', // Indigo
        '#f97316', // Orange foncé
        '#14b8a6', // Teal
        '#a855f7', // Violet clair
        '#ef4444', // Rouge
        '#84cc16', // Vert lime
    ];
    
    // Utiliser le hash du nom pour choisir une couleur de manière cohérente
    $hash = crc32($name);
    $index = abs($hash) % count($colors);
    
    return $colors[$index];
}

/**
 * Affiche un avatar avec initiales
 * @param User|array $user Objet User ou tableau avec les données utilisateur
 * @param string $size Taille CSS (ex: 'w-10 h-10', 'w-16 h-16')
 * @param string $textSize Taille du texte CSS (ex: 'text-sm', 'text-lg')
 * @return string Code HTML de l'avatar
 */
function renderAvatar($user, string $size = 'w-10 h-10', string $textSize = 'text-sm'): string {
    $initials = getInitials($user);
    
    // Obtenir le nom pour la couleur
    $name = '';
    if (is_object($user)) {
        $name = $user->username ?? $user->getUsername() ?? '';
    } else if (is_array($user)) {
        $name = $user['username'] ?? '';
    }
    
    $color = getAvatarColor($name);
    
    return '<div class="' . $size . ' rounded-full flex items-center justify-center font-bold text-white ' . $textSize . '" style="background-color: ' . $color . ';">' 
           . htmlspecialchars($initials) 
           . '</div>';
}

/**
 * Gestionnaire des documents PDF
 */
class PDFManager {
    private static $instance = null;
    private $config;
    private $pdfRepository;
    
    private function __construct() {
        $this->config = Config::getInstance();
        $this->pdfRepository = new PDFRepository();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function validateAccess(User $user, Book $book): bool {
        // Vérifier si l'utilisateur a accès au livre
        if (!$book->getId() || !$user->getId()) {
            return false;
        }
        
        // Vérifier si le livre a un chemin de fichier
        $filePath = $book->getFilePath();
        if (empty($filePath)) {
            return false;
        }
        
        // Vérifier si le fichier existe et est valide
        return $this->pdfRepository->validatePath($filePath);
    }
    
    public function getFilePath(Book $book): string {
        $originalPath = $book->getFilePath();
        
        if (empty($originalPath)) {
            throw new Exception("Le livre n'a pas de fichier PDF associé");
        }
        
        return $this->pdfRepository->resolvePath($originalPath);
    }
    
    public function getMetadata(Book $book): array {
        try {
            return $this->pdfRepository->getMetadata($book->getFilePath());
        } catch (Exception $e) {
            error_log("Error getting PDF metadata: " . $e->getMessage());
            return [];
        }
    }
}