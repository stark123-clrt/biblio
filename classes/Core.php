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
