<?php

/**
 * =================================================================
 * 🎯 REPOSITORIES - GESTIONNAIRES DE DONNÉES AVEC OBJETS
 * =================================================================
 *
 * Classes responsables de l'accès aux données de la base
 * Principe : Toujours retourner des objets typés, jamais des tableaux bruts
 */

// ========================
// 🏗️ CLASSE REPOSITORY ABSTRAITE
// ========================

abstract class BaseRepository
{
    protected $db;
    protected $connection;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->connection = $this->db->getConnection();
    }

    protected function prepare(string $sql): PDOStatement
    {
        return $this->connection->prepare($sql);
    }

    protected function query(string $sql)
    {
        return $this->connection->query($sql);
    }

    protected function lastInsertId(): int
    {
        return (int)$this->connection->lastInsertId();
    }

    protected function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    protected function commit(): bool
    {
        return $this->connection->commit();
    }

    protected function rollback(): bool
    {
        return $this->connection->rollback();
    }
}

// ========================
// 👤 USER REPOSITORY
// ========================

class UserRepository extends BaseRepository
{
    /**
     * ✅ Retourne un objet User ou null
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }



    public function findByEmailVerificationToken(string $token): ?User
    {
        $stmt = $this->prepare("SELECT * FROM users WHERE email_verification_token = :token");
        $stmt->bindValue(':token', $token);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }


    public function markEmailAsVerified(int $userId): bool
    {
        $stmt = $this->prepare("UPDATE users SET 
                                email_verified_at = NOW(), 
                                email_verification_token = NULL 
                                WHERE id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }



    public function updateEmailVerificationToken(int $userId, string $token): bool
    {
        $stmt = $this->prepare("UPDATE users SET email_verification_token = :token WHERE id = :id");
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }



    /**
     * ✅ Retourne un objet User ou null
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }

    /**
     * ✅ Retourne un objet User ou null
     */
    public function findByUsername(string $username): ?User
    {
        $stmt = $this->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }


    public function findByPasswordResetToken(string $token): ?User
    {
        $stmt = $this->prepare("SELECT * FROM users WHERE password_reset_token = :token");
        $stmt->bindValue(':token', $token);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }


    public function savePasswordResetToken(int $userId, string $token, string $expires): bool
    {
        $stmt = $this->prepare("UPDATE users SET 
                                password_reset_token = :token, 
                                password_reset_expires = :expires 
                                WHERE id = :id");
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':expires', $expires);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }



    public function clearPasswordResetToken(int $userId): bool
    {
        $stmt = $this->prepare("UPDATE users SET 
                                password_reset_token = NULL, 
                                password_reset_expires = NULL 
                                WHERE id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }



    public function resetPassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->prepare("UPDATE users SET 
                                password = :password,
                                password_reset_token = NULL, 
                                password_reset_expires = NULL,
                                updated_at = NOW()
                                WHERE id = :id");
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }


    public function cleanExpiredPasswordResetTokens(): int
    {
        $stmt = $this->prepare("UPDATE users SET 
                                password_reset_token = NULL, 
                                password_reset_expires = NULL 
                                WHERE password_reset_expires IS NOT NULL 
                                AND password_reset_expires < NOW()");
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * ✅ Retourne un tableau d'objets User
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch()) {
            $users[] = new User($data);
        }

        return $users;
    }

    /**
     * Vérifier si un email ou nom d'utilisateur existe déjà
     */
    public function existsByEmailOrUsername(string $email, string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM users WHERE (email = :email OR username = :username)";
        $params = [':email' => $email, ':username' => $username];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * ✅ Créer un utilisateur avec objet User
     */
    public function create(User $user): bool
    {
        $sql = "INSERT INTO users (username, password, email, first_name, last_name, role, is_active, email_verification_token, created_at) 
                VALUES (:username, :password, :email, :first_name, :last_name, :role, :is_active, :email_verification_token, NOW())";

        $stmt = $this->prepare($sql);
        $data = $user->toArray();

        $stmt->bindValue(':username', $data['username']);
        $stmt->bindValue(':password', $data['password']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':role', $data['role'] ?? 'user');
        $stmt->bindValue(':is_active', $data['is_active'] ?? true, PDO::PARAM_BOOL);

        $stmt->bindValue(':email_verification_token', $data['email_verification_token']);

        if ($stmt->execute()) {
            $user->setId($this->lastInsertId());
            return true;
        }

        return false;
    }

    /**
     * ✅ Mettre à jour un utilisateur avec objet User
     */
    public function update(User $user): bool
    {
        $sql = "UPDATE users SET 
                username = :username, 
                email = :email, 
                first_name = :first_name, 
                last_name = :last_name, 
                profile_picture = :profile_picture,
                updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->prepare($sql);
        $data = $user->toArray();

        $stmt->bindValue(':username', $data['username']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':profile_picture', $data['profile_picture']);
        $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updateLastLogin(int $userId): bool
    {
        $stmt = $this->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countAll(): int
    {
        $stmt = $this->query("SELECT COUNT(*) FROM users");
        return (int)$stmt->fetchColumn();
    }

    public function delete(int $userId): bool
    {
        $stmt = $this->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

// ========================
// 📖 BOOK REPOSITORY
// ========================

class BookRepository extends BaseRepository
{
    /**
     * ✅ Retourne un objet Book avec toutes les infos
     */
    public function findById(int $id): ?Book
    {
        $sql = "SELECT b.*, c.name as category_name,
                COALESCE(AVG(comm.rating), 0) as avg_rating,
                COUNT(DISTINCT comm.id) as total_reviews
                FROM books b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
                WHERE b.id = :id 
                GROUP BY b.id
                LIMIT 1";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new Book($data) : null;
    }

    /**
     * ✅ Retourne un tableau d'objets Book
     */
    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT b.*, c.name as category_name,
                COALESCE(AVG(comm.rating), 0) as avg_rating,
                COUNT(DISTINCT comm.id) as total_reviews
                FROM books b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
                GROUP BY b.id
                ORDER BY b.created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }

    /**
     * ✅ Retourne un tableau d'objets Book en vedette
     */
    public function findFeatured(int $limit = 4): array
    {
        $sql = "SELECT b.*, c.name as category_name,
                COALESCE(AVG(comm.rating), 0) as avg_rating,
                COUNT(DISTINCT comm.id) as total_reviews
                FROM books b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
                WHERE b.is_featured = 1 
                GROUP BY b.id
                ORDER BY b.created_at DESC 
                LIMIT :limit";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }

    /**
     * ✅ Retourne un tableau d'objets Book récents
     */
    public function findRecent(int $limit = 8): array
    {
        $sql = "SELECT b.*, c.name as category_name,
                COALESCE(AVG(comm.rating), 0) as avg_rating,
                COUNT(DISTINCT comm.id) as total_reviews
                FROM books b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
                GROUP BY b.id
                ORDER BY b.created_at DESC 
                LIMIT :limit";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }

    /**
     * ✅ Recherche avec objets Book
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        return $this->searchWithSort($query, 'newest', $limit, $offset);
    }

    /**
     * ✅ Livres par catégorie avec objets
     */

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        return $this->findByCategoryWithSort($categoryId, 'newest', $limit, $offset);
    }

    /**
     * ✅ Créer un livre avec objet Book
     */
    public function create(Book $book): bool
    {
        $sql = "INSERT INTO books (title, author, description, cover_image, pdf_path, pages_count, 
                publication_year, publisher, is_featured, views_count, created_at) 
                VALUES (:title, :author, :description, :cover_image, :pdf_path, :pages_count, 
                :publication_year, :publisher, :is_featured, :views_count, NOW())";

        $stmt = $this->prepare($sql);
        $data = $book->toArray();

        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':author', $data['author']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':cover_image', $data['cover_image']);
        $stmt->bindValue(':pdf_path', $data['pdf_path']);
        $stmt->bindValue(':pages_count', $data['pages_count'], PDO::PARAM_INT);
        $stmt->bindValue(':publication_year', $data['publication_year'], PDO::PARAM_INT);
        $stmt->bindValue(':publisher', $data['publisher']);
        $stmt->bindValue(':is_featured', $data['is_featured'] ?? false, PDO::PARAM_BOOL);
        $stmt->bindValue(':views_count', $data['views_count'] ?? 0, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $book->setId($this->lastInsertId());
            return true;
        }

        return false;
    }

    /**
     * ✅ Mettre à jour un livre avec objet Book
     */
    public function update(Book $book): bool
    {
        $sql = "UPDATE books SET 
                title = :title, 
                author = :author, 
                description = :description, 
                cover_image = :cover_image,
                pdf_path = :pdf_path,
                pages_count = :pages_count,
                publication_year = :publication_year,
                publisher = :publisher,
                is_featured = :is_featured,
                updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->prepare($sql);
        $data = $book->toArray();

        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':author', $data['author']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':cover_image', $data['cover_image']);
        $stmt->bindValue(':pdf_path', $data['pdf_path']);
        $stmt->bindValue(':pages_count', $data['pages_count'], PDO::PARAM_INT);
        $stmt->bindValue(':publication_year', $data['publication_year'], PDO::PARAM_INT);
        $stmt->bindValue(':publisher', $data['publisher']);
        $stmt->bindValue(':is_featured', $data['is_featured'], PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function incrementViews(int $bookId): bool
    {
        $stmt = $this->prepare("UPDATE books SET views_count = COALESCE(views_count, 0) + 1 WHERE id = :id");
        $stmt->bindValue(':id', $bookId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countAll(): int
    {
        $stmt = $this->query("SELECT COUNT(*) FROM books");
        return (int)$stmt->fetchColumn();
    }

    public function countTotalViews(): int
    {
        $stmt = $this->query("SELECT SUM(COALESCE(views_count, 0)) FROM books");
        return (int)$stmt->fetchColumn();
    }

    public function delete(int $bookId): bool
    {
        $stmt = $this->prepare("DELETE FROM books WHERE id = :id");
        $stmt->bindValue(':id', $bookId, PDO::PARAM_INT);

        return $stmt->execute();
    }


    public function findByCategoryWithSort(int $categoryId, string $sort = 'newest', int $limit = 20, int $offset = 0): array
    {
    // Construire la clause ORDER BY selon le tri
        $orderBy = match ($sort) {
            'oldest' => 'b.created_at ASC',
            'title_asc' => 'b.title ASC',
            'title_desc' => 'b.title DESC',
            'popular' => 'b.views_count DESC',
            'rating' => 'COALESCE(AVG(comm.rating), 0) DESC',
            default => 'b.created_at DESC' // newest
        };

        $sql = "SELECT b.*, c.name as category_name,
            COALESCE(AVG(comm.rating), 0) as avg_rating,
            COUNT(DISTINCT comm.id) as total_reviews
            FROM books b
            JOIN book_categories bc ON b.id = bc.book_id
            JOIN categories c ON bc.category_id = c.id
            LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
            WHERE bc.category_id = :category_id
            GROUP BY b.id
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }

    public function countByCategory(int $categoryId): int
    {
        $sql = "SELECT COUNT(DISTINCT b.id) 
            FROM books b
            JOIN book_categories bc ON b.id = bc.book_id
            WHERE bc.category_id = :category_id";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }


    public function findAllWithSort(string $sort = 'newest', int $limit = 20, int $offset = 0): array
    {
     // Construire la clause ORDER BY selon le tri
        $orderBy = match ($sort) {
              'oldest' => 'b.created_at ASC',
              'title_asc' => 'b.title ASC',
              'title_desc' => 'b.title DESC',
              'popular' => 'b.views_count DESC',
              'rating' => 'COALESCE(AVG(comm.rating), 0) DESC',
              default => 'b.created_at DESC' // newest
        };

        $sql = "SELECT b.*, c.name as category_name,
            COALESCE(AVG(comm.rating), 0) as avg_rating,
            COUNT(DISTINCT comm.id) as total_reviews
            FROM books b 
            LEFT JOIN book_categories bc ON b.id = bc.book_id 
            LEFT JOIN categories c ON bc.category_id = c.id 
            LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
            GROUP BY b.id
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
              $books[] = new Book($data);
        }

        return $books;
    }

/**
 * ✅ Recherche avec tri et pagination
 */
    public function searchWithSort(string $query, string $sort = 'newest', int $limit = 20, int $offset = 0): array
    {
        $searchTerm = "%{$query}%";

        // Construire la clause ORDER BY selon le tri
        $orderBy = match ($sort) {
            'oldest' => 'b.created_at ASC',
            'title_asc' => 'b.title ASC',
            'title_desc' => 'b.title DESC',
            'popular' => 'b.views_count DESC',
            'rating' => 'COALESCE(AVG(comm.rating), 0) DESC',
            default => 'b.created_at DESC' // newest
        };

        $sql = "SELECT b.*, c.name as category_name,
            COALESCE(AVG(comm.rating), 0) as avg_rating,
            COUNT(DISTINCT comm.id) as total_reviews
            FROM books b 
            LEFT JOIN book_categories bc ON b.id = bc.book_id 
            LEFT JOIN categories c ON bc.category_id = c.id 
            LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
            WHERE b.title LIKE :search 
            OR b.author LIKE :search 
            OR b.description LIKE :search 
            GROUP BY b.id
            ORDER BY 
                CASE WHEN b.title LIKE :search THEN 1 ELSE 2 END,
                {$orderBy}
            LIMIT :limit OFFSET :offset";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':search', $searchTerm);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }

/**
 * ✅ Compter les résultats de recherche
 */
    public function countSearch(string $query): int
    {
        $searchTerm = "%{$query}%";

        $sql = "SELECT COUNT(DISTINCT b.id) 
            FROM books b 
            WHERE b.title LIKE :search 
            OR b.author LIKE :search 
            OR b.description LIKE :search";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':search', $searchTerm);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }


    public function findBooksWithComments(): array
    {
        $sql = "SELECT DISTINCT b.id, b.title
            FROM books b
            JOIN comments c ON b.id = c.book_id
            WHERE c.is_validated = 1
            ORDER BY b.title ASC";

        $stmt = $this->prepare($sql);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }
}

// ========================
// 📚 CATEGORY REPOSITORY
// ========================

class CategoryRepository extends BaseRepository
{
    /**
     * ✅ Retourne un objet Category ou null
     */
    public function findById(int $id): ?Category
    {
        $stmt = $this->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new Category($data) : null;
    }

    /**
     * ✅ Retourne un tableau d'objets Category
     */
    public function findAll(): array
    {
        $stmt = $this->prepare("SELECT * FROM categories ORDER BY name ASC");
        $stmt->execute();

        $categories = [];
        while ($data = $stmt->fetch()) {
            $categories[] = new Category($data);
        }

        return $categories;
    }

    /**
     * ✅ Retourne un tableau d'objets Category avec comptage
     */
    public function findWithBookCount(int $limit = 6): array
    {
        $sql = "SELECT c.*, COUNT(DISTINCT bc.book_id) as book_count
                FROM categories c
                LEFT JOIN book_categories bc ON c.id = bc.category_id
                GROUP BY c.id
                ORDER BY book_count DESC
                LIMIT :limit";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $categories = [];
        while ($data = $stmt->fetch()) {
            $categories[] = new Category($data);
        }

        return $categories;
    }

    /**
     * ✅ Créer une catégorie avec objet Category
     */
    public function create(Category $category): bool
    {
        $sql = "INSERT INTO categories (name, description, color, icon, created_at) 
                VALUES (:name, :description, :color, :icon, NOW())";

        $stmt = $this->prepare($sql);
        $data = $category->toArray();

        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':color', $data['color']);
        $stmt->bindValue(':icon', $data['icon']);

        if ($stmt->execute()) {
            $category->setId($this->lastInsertId());
            return true;
        }

        return false;
    }

    /**
     * ✅ Mettre à jour une catégorie avec objet Category
     */
    public function update(Category $category): bool
    {
        $sql = "UPDATE categories SET 
                name = :name, 
                description = :description, 
                color = :color, 
                icon = :icon
                WHERE id = :id";

        $stmt = $this->prepare($sql);
        $data = $category->toArray();

        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':color', $data['color']);
        $stmt->bindValue(':icon', $data['icon']);
        $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countAll(): int
    {
        $stmt = $this->query("SELECT COUNT(*) FROM categories");
        return (int)$stmt->fetchColumn();
    }

    public function delete(int $categoryId): bool
    {
        $stmt = $this->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->bindValue(':id', $categoryId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}




class CommentRepository extends BaseRepository
{
    public function findById(int $id): ?Comment
    {
        $sql = "SELECT c.*, u.username, u.profile_picture as user_profile_picture, 
                b.title as book_title, b.cover_image
                FROM comments c
                JOIN users u ON c.user_id = u.id  
                JOIN books b ON c.book_id = b.id
                WHERE c.id = :id";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new Comment($data) : null;
    }

    /**
     * Retourne un tableau d'objets Comment (témoignages)
     */
    public function findTestimonials(int $limit = 3): array
    {
        $sql = "SELECT c.*, u.username, u.profile_picture as user_profile_picture, 
                b.title as book_title, b.cover_image, b.id as book_id
                FROM comments c
                JOIN users u ON c.user_id = u.id  
                JOIN books b ON c.book_id = b.id
                WHERE c.is_validated = 1 AND c.rating >= 4
                ORDER BY c.created_at DESC
                LIMIT :limit";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $comments = [];
        while ($data = $stmt->fetch()) {
            $comments[] = new Comment($data);
        }

        return $comments;
    }

    /**
     * Retourne un tableau d'objets Comment par livre
     */
    public function findByBook(int $bookId, bool $onlyValidated = true): array
    {
        $sql = "SELECT c.*, u.username, u.profile_picture as user_profile_picture, 
                b.title as book_title, b.cover_image
                FROM comments c
                JOIN users u ON c.user_id = u.id
                JOIN books b ON c.book_id = b.id
                WHERE c.book_id = :book_id";

        if ($onlyValidated) {
            $sql .= " AND c.is_validated = 1";
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        $comments = [];
        while ($data = $stmt->fetch()) {
            $comments[] = new Comment($data);
        }

        return $comments;
    }

    /**
     * Retourne un tableau d'objets Comment par utilisateur
     */
    public function findByUser(int $userId): array
    {
        $sql = "SELECT c.*, b.title as book_title, b.cover_image
                FROM comments c
                JOIN books b ON c.book_id = b.id
                WHERE c.user_id = :user_id
                ORDER BY c.created_at DESC";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $comments = [];
        while ($data = $stmt->fetch()) {
            $comments[] = new Comment($data);
        }

        return $comments;
    }

    /**
     * Créer un commentaire avec objet Comment
     */
    public function create(Comment $comment): bool
    {
        $sql = "INSERT INTO comments (user_id, book_id, rating, comment_text, is_validated, created_at) 
                VALUES (:user_id, :book_id, :rating, :comment_text, :is_validated, NOW())";

        $stmt = $this->prepare($sql);
        $data = $comment->toArray();

        $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $data['book_id'], PDO::PARAM_INT);
        $stmt->bindValue(':rating', $data['rating'], PDO::PARAM_INT);
        $stmt->bindValue(':comment_text', $data['comment_text']);
        $stmt->bindValue(':is_validated', $data['is_validated'] ?? 0, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            $comment->setId($this->lastInsertId());
            return true;
        }

        return false;
    }

    public function delete(int $commentId, int $userId): bool
    {
        $stmt = $this->prepare("DELETE FROM comments WHERE id = :comment_id AND user_id = :user_id");
        $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getAverageRating(int $bookId): float
    {
        $stmt = $this->prepare("SELECT AVG(rating) FROM comments WHERE book_id = :book_id AND is_validated = 1");
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        return (float)$stmt->fetchColumn() ?: 0.0;
    }

    public function countByBook(int $bookId, bool $onlyValidated = true): int
    {
        $sql = "SELECT COUNT(*) FROM comments WHERE book_id = :book_id";

        if ($onlyValidated) {
            $sql .= " AND is_validated = 1";
        }

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->prepare("SELECT COUNT(*) FROM comments WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function countAll(): int
    {
        $stmt = $this->prepare("SELECT COUNT(*) FROM comments WHERE is_validated = 1");
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function findTestimonialsWithFilters(int $bookId = 0, int $rating = 0, string $sort = 'recent', int $limit = 20, int $offset = 0): array
    {
    // Construire la clause WHERE pour les filtres
        $whereConditions = ["c.is_validated = 1", "c.rating >= 4"]; // Témoignages = commentaires validés avec bonne note
        $params = [];

        if ($bookId > 0) {
            $whereConditions[] = "c.book_id = :book_id";
            $params[':book_id'] = $bookId;
        }

        if ($rating > 0) {
            $whereConditions[] = "c.rating = :rating";
            $params[':rating'] = $rating;
        }

        $whereClause = implode(" AND ", $whereConditions);

    // Construire la clause ORDER BY selon le tri
        $orderBy = match ($sort) {
            'oldest' => 'c.created_at ASC',
            'highest' => 'c.rating DESC, c.created_at DESC',
            'lowest' => 'c.rating ASC, c.created_at DESC',
            default => 'c.created_at DESC' // recent
        };

        $sql = "SELECT c.*, u.username, u.profile_picture as user_profile_picture, 
            b.title as book_title, b.cover_image
            FROM comments c
            JOIN users u ON c.user_id = u.id  
            JOIN books b ON c.book_id = b.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset";

        $stmt = $this->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $comments = [];
        while ($data = $stmt->fetch()) {
            $comments[] = new Comment($data);
        }

        return $comments;
    }

/**
 * ✅ Compter les témoignages avec filtres
 */
    public function countTestimonialsWithFilters(int $bookId = 0, int $rating = 0): int
    {
        // Construire la clause WHERE pour les filtres
        $whereConditions = ["c.is_validated = 1", "c.rating >= 4"];
        $params = [];

        if ($bookId > 0) {
            $whereConditions[] = "c.book_id = :book_id";
            $params[':book_id'] = $bookId;
        }

        if ($rating > 0) {
            $whereConditions[] = "c.rating = :rating";
            $params[':rating'] = $rating;
        }

        $whereClause = implode(" AND ", $whereConditions);

        $sql = "SELECT COUNT(*) FROM comments c WHERE {$whereClause}";

        $stmt = $this->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }



// À ajouter dans la classe CommentRepository existante

/**
 * ✅ Vérifier si un utilisateur a déjà commenté un livre
 */
    public function findUserCommentForBook(int $userId, int $bookId): ?Comment
    {
        $sql = "SELECT c.*, u.username, b.title as book_title, b.cover_image
            FROM comments c
            JOIN users u ON c.user_id = u.id  
            JOIN books b ON c.book_id = b.id
            WHERE c.user_id = :user_id AND c.book_id = :book_id";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new Comment($data) : null;
    }

/**
 * ✅ Mettre à jour un commentaire existant
 */
    public function updateComment(int $commentId, int $userId, string $commentText, int $rating): bool
    {
        $sql = "UPDATE comments SET 
            comment_text = :comment_text,
            rating = :rating
            WHERE id = :id AND user_id = :user_id";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':comment_text', $commentText);
        $stmt->bindValue(':rating', $rating, PDO::PARAM_INT);
        $stmt->bindValue(':id', $commentId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }


/**
 * ✅ Créer ou mettre à jour un commentaire (avec vérification de lecture)
 */
    public function createOrUpdate(int $userId, int $bookId, string $commentText, int $rating): array
    {
        // ✅ VÉRIFIER QUE L'UTILISATEUR A VRAIMENT COMMENCÉ À LIRE LE LIVRE
        $stmt = $this->prepare("SELECT last_page_read FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'action' => 'not_in_library', 'message' => 'Vous devez d\'abord ajouter ce livre à votre bibliothèque.'];
        }

        $libraryData = $stmt->fetch();
        $lastPageRead = $libraryData['last_page_read'];

        // ✅ VÉRIFICATION : L'utilisateur doit avoir lu au moins 2 pages
        if ($lastPageRead <= 1) {
            return ['success' => false, 'action' => 'not_started_reading', 'message' => 'Vous devez avoir commencé à lire ce livre pour pouvoir le commenter. Ouvrez le livre et lisez au moins quelques pages.'];
        }

        // ✅ MAINTENANT VÉRIFIER SI UN COMMENTAIRE EXISTE DÉJÀ
        $checkStmt = $this->prepare("SELECT id FROM comments WHERE user_id = :user_id AND book_id = :book_id");
        $checkStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $checkStmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            // METTRE À JOUR le commentaire existant
            $commentId = $checkStmt->fetchColumn();
            $updateStmt = $this->prepare("UPDATE comments SET comment_text = :comment_text, rating = :rating WHERE id = :id");
            $updateStmt->bindValue(':comment_text', $commentText);
            $updateStmt->bindValue(':rating', $rating, PDO::PARAM_INT);
            $updateStmt->bindValue(':id', $commentId, PDO::PARAM_INT);

            if ($updateStmt->execute()) {
                return ['success' => true, 'action' => 'updated', 'message' => 'Votre commentaire a été mis à jour avec succès !'];
            } else {
                return ['success' => false, 'action' => 'update_failed', 'message' => 'Erreur lors de la mise à jour du commentaire.'];
            }
        } else {
            // CRÉER un nouveau commentaire
            $insertStmt = $this->prepare("INSERT INTO comments (user_id, book_id, rating, comment_text, is_validated, created_at) VALUES (:user_id, :book_id, :rating, :comment_text, 1, NOW())");
            $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $insertStmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
            $insertStmt->bindValue(':rating', $rating, PDO::PARAM_INT);
            $insertStmt->bindValue(':comment_text', $commentText);

            if ($insertStmt->execute()) {
                return ['success' => true, 'action' => 'created', 'message' => 'Votre commentaire a été publié avec succès !'];
            } else {
                return ['success' => false, 'action' => 'create_failed', 'message' => 'Erreur lors de la création du commentaire.'];
            }
        }
    }
}

// ========================
// 📝 NOTE REPOSITORY
// ========================

class NoteRepository extends BaseRepository
{
    /**
     * ✅ Retourne un objet Note ou null
     */
    public function findById(int $id): ?Note
    {
        $sql = "SELECT n.*, b.title as book_title, b.cover_image as book_cover_image
                FROM notes n
                JOIN books b ON n.book_id = b.id
                WHERE n.id = :id";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        return $data ? new Note($data) : null;
    }

    /**
     * ✅ Retourne un tableau d'objets Note par utilisateur
     */
    public function findByUser(int $userId, ?int $bookId = null): array
    {
        $sql = "SELECT n.*, b.title as book_title, b.cover_image as book_cover_image
                FROM notes n
                JOIN books b ON n.book_id = b.id
                WHERE n.user_id = :user_id";

        $params = [':user_id' => $userId];

        if ($bookId) {
            $sql .= " AND n.book_id = :book_id";
            $params[':book_id'] = $bookId;
        }

        $sql .= " ORDER BY n.created_at DESC";

        $stmt = $this->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        $notes = [];
        while ($data = $stmt->fetch()) {
            $notes[] = new Note($data);
        }

        return $notes;
    }

    /**
     * ✅ Créer une note avec objet Note
     */
    public function create(Note $note): bool
    {
        $sql = "INSERT INTO notes (user_id, book_id, note_text, page_number, is_public, created_at) 
                VALUES (:user_id, :book_id, :note_text, :page_number, :is_public, NOW())";

        $stmt = $this->prepare($sql);
        $data = $note->toArray();

        $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $data['book_id'], PDO::PARAM_INT);
        $stmt->bindValue(':note_text', $data['note_text']);
        $stmt->bindValue(':page_number', $data['page_number'], PDO::PARAM_INT);
        $stmt->bindValue(':is_public', $data['is_public'] ?? false, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            $note->setId($this->lastInsertId());
            return true;
        }

        return false;
    }

    /**
     * ✅ Mettre à jour une note avec objet Note
     */
    public function update(Note $note): bool
    {
        $sql = "UPDATE notes SET 
                note_text = :note_text, 
                page_number = :page_number, 
                is_public = :is_public,
                updated_at = NOW()
                WHERE id = :id AND user_id = :user_id";

        $stmt = $this->prepare($sql);
        $data = $note->toArray();

        $stmt->bindValue(':note_text', $data['note_text']);
        $stmt->bindValue(':page_number', $data['page_number'], PDO::PARAM_INT);
        $stmt->bindValue(':is_public', $data['is_public'], PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(int $noteId, int $userId): bool
    {
        $stmt = $this->prepare("DELETE FROM notes WHERE id = :note_id AND user_id = :user_id");
        $stmt->bindValue(':note_id', $noteId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->prepare("SELECT COUNT(*) FROM notes WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }


    public function findByUserWithFilters(int $userId, int $bookId = 0, string $search = '', string $sort = 'newest'): array
    {
    // Construire la clause WHERE
        $whereConditions = ["n.user_id = :user_id"];
        $params = [':user_id' => $userId];

        if ($bookId > 0) {
            $whereConditions[] = "n.book_id = :book_id";
            $params[':book_id'] = $bookId;
        }

        if (!empty($search)) {
            $whereConditions[] = "(n.note_text LIKE :search OR b.title LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(" AND ", $whereConditions);

    // Construire la clause ORDER BY selon le tri
        $orderBy = match ($sort) {
            'oldest' => 'n.created_at ASC',
            'book' => 'b.title ASC, n.page_number ASC',
            'page' => 'b.title ASC, n.page_number ASC',
            default => 'n.created_at DESC' // newest
        };

        $sql = "SELECT n.*, b.title as book_title, b.cover_image as book_cover_image
            FROM notes n
            JOIN books b ON n.book_id = b.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}";

        $stmt = $this->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();

        $notes = [];
        while ($data = $stmt->fetch()) {
            $notes[] = new Note($data);
        }

        return $notes;
    }

/**
 * ✅ Récupérer les livres de l'utilisateur qui ont des notes (RETOURNE DES OBJETS Book)
 */
    public function getUserBooksWithNotes(int $userId): array
    {
        $sql = "SELECT DISTINCT b.id, b.title, b.cover_image
            FROM books b
            INNER JOIN notes n ON b.id = n.book_id
            WHERE n.user_id = :user_id
            ORDER BY b.title ASC";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            $books[] = new Book($data);
        }

        return $books;
    }

/**
 * ✅ Compter les notes d'un utilisateur avec filtres
 */
    public function countByUserWithFilters(int $userId, int $bookId = 0, string $search = ''): int
    {
        // Construire la clause WHERE
        $whereConditions = ["n.user_id = :user_id"];
        $params = [':user_id' => $userId];

        if ($bookId > 0) {
            $whereConditions[] = "n.book_id = :book_id";
            $params[':book_id'] = $bookId;
        }

        if (!empty($search)) {
            $whereConditions[] = "(n.note_text LIKE :search OR b.title LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(" AND ", $whereConditions);

        $sql = "SELECT COUNT(*) 
            FROM notes n
            JOIN books b ON n.book_id = b.id
            WHERE {$whereClause}";

        $stmt = $this->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
}

// ========================
// LIBRARY REPOSITORY
// ========================

class LibraryRepository extends BaseRepository
{
    /**
     * Retourne un tableau d'objets Book de la bibliothèque utilisateur
     */
    public function getUserLibrary(int $userId): array
    {
        $sql = "SELECT b.*, ul.last_page_read, ul.added_at, ul.last_read_at, ul.is_favorite,
                c.name as category_name,
                COALESCE(AVG(comm.rating), 0) as avg_rating,
                COUNT(DISTINCT comm.id) as total_reviews
                FROM user_library ul
                JOIN books b ON ul.book_id = b.id
                LEFT JOIN book_categories bc ON b.id = bc.book_id
                LEFT JOIN categories c ON bc.category_id = c.id
                LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
                WHERE ul.user_id = :user_id
                GROUP BY b.id
                ORDER BY ul.last_read_at DESC";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $books = [];
        while ($data = $stmt->fetch()) {
            // Créer un objet Book enrichi avec les données de la bibliothèque
            $book = new Book($data);
            // Ajouter les propriétés spécifiques à la bibliothèque
            $book->setLastPageRead($data['last_page_read'] ?? 0);
            $book->setAddedAt($data['added_at'] ?? null);
            $book->setLastReadAt($data['last_read_at'] ?? null);
            $book->setIsFavoriteInLibrary($data['is_favorite'] ?? false);

            $books[] = $book;
        }

        return $books;
    }

    public function addToLibrary(int $userId, int $bookId): bool
    {
        // Vérifier si le livre n'est pas déjà dans la bibliothèque
        if ($this->isInLibrary($userId, $bookId)) {
            return false;
        }

        $stmt = $this->prepare("INSERT INTO user_library (user_id, book_id, added_at) VALUES (:user_id, :book_id, NOW())");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function removeFromLibrary(int $userId, int $bookId): bool
    {
        $stmt = $this->prepare("DELETE FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function isInLibrary(int $userId, int $bookId): bool
    {
        $stmt = $this->prepare("SELECT COUNT(*) FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function updateReadingProgress(int $userId, int $bookId, int $pageNumber): bool
    {
        $stmt = $this->prepare("
            UPDATE user_library 
            SET last_page_read = :page_number, last_read_at = NOW() 
            WHERE user_id = :user_id AND book_id = :book_id
        ");

        $stmt->bindValue(':page_number', $pageNumber, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function toggleFavorite(int $userId, int $bookId): bool
    {
        $stmt = $this->prepare("
            UPDATE user_library 
            SET is_favorite = NOT COALESCE(is_favorite, 0) 
            WHERE user_id = :user_id AND book_id = :book_id
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getUserStats(int $userId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_books,
                    COUNT(CASE WHEN last_page_read > 1 THEN 1 END) as started_books,
                    COUNT(CASE WHEN is_favorite = 1 THEN 1 END) as favorite_books
                FROM user_library 
                WHERE user_id = :user_id";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: [
            'total_books' => 0,
            'started_books' => 0,
            'favorite_books' => 0
        ];
    }


    /**
     * Vérifier si un livre est favori pour un utilisateur
     */
    public function isFavorite(int $userId, int $bookId): bool
    {
        $stmt = $this->prepare("SELECT is_favorite FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ? (bool)$result['is_favorite'] : false;
    }

    /**
     * Récupérer les informations de bibliothèque pour un livre spécifique
     */
    public function getLibraryBookInfo(int $userId, int $bookId): ?array
    {
        $stmt = $this->prepare("SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }
}





class ReadingHistoryRepository extends BaseRepository
{
    /**
     * Retourne un tableau d'objets ReadingHistory par utilisateur
     */
    public function findRecentByUser(int $userId, int $limit = 5): array
    {
        $sql = "SELECT rh.*, b.title as book_title, b.cover_image as book_cover_image
                FROM reading_history rh
                JOIN books b ON rh.book_id = b.id
                WHERE rh.user_id = :user_id
                ORDER BY rh.timestamp DESC
                LIMIT :limit";

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $activities = [];
        while ($data = $stmt->fetch()) {
            $activities[] = new ReadingHistory($data);
        }

        return $activities;
    }

    /**
     * ✅ Créer une nouvelle activité
     */
    public function create(ReadingHistory $activity): bool
    {
        $sql = "INSERT INTO reading_history (user_id, book_id, action, page_number, timestamp) 
                VALUES (:user_id, :book_id, :action, :page_number, NOW())";

        $stmt = $this->prepare($sql);
        $data = $activity->toArray();

        $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $data['book_id'], PDO::PARAM_INT);
        $stmt->bindValue(':action', $data['action']);
        $stmt->bindValue(':page_number', $data['page_number'], PDO::PARAM_INT);

        if ($stmt->execute()) {
            $activity->setId($this->lastInsertId());
            return true;
        }

        return false;
    }
}




class ForumCategoryRepository {
    private $conn;
    
    public function __construct() {
        $this->conn = getDatabase();
    }
    
    /**
     * Récupérer toutes les catégories avec statistiques
     */
    public function findAllWithStats(): array {
        $query = "SELECT 
                    fc.*,
                    COUNT(DISTINCT ft.id) as topics_count,
                    COUNT(DISTINCT fp.id) as posts_count
                  FROM forum_categories fc
                  LEFT JOIN forum_topics ft ON fc.id = ft.category_id
                  LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
                  GROUP BY fc.id
                  ORDER BY fc.order_position ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $category = new ForumCategory($row);
            $category->topics_count = (int)$row['topics_count'];
            $category->posts_count = (int)$row['posts_count'];
            
            // Récupérer le dernier post
            $category->last_post = $this->getLastPostForCategory($category->id);
            
            $categories[] = $category;
        }
        
        return $categories;
    }
    
    /**
     * Trouver une catégorie par ID
     */
    public function findById(int $id): ?ForumCategory {
        $query = "SELECT * FROM forum_categories WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new ForumCategory($row) : null;
    }
    
    /**
     * Récupérer le dernier post d'une catégorie
     */
    private function getLastPostForCategory(int $categoryId): ?ForumPost {
        $query = "SELECT fp.*, u.username, u.profile_picture
                  FROM forum_posts fp
                  INNER JOIN forum_topics ft ON fp.topic_id = ft.id
                  INNER JOIN users u ON fp.user_id = u.id
                  WHERE ft.category_id = :category_id
                  ORDER BY fp.created_at DESC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $post = new ForumPost($row);
            $post->user = new User($row);
            return $post;
        }
        
        return null;
    }
}

/**
 * Repository pour les topics du forum
 */
class ForumTopicRepository {
    private $conn;
    
    public function __construct() {
        $this->conn = getDatabase();
    }
    
    /**
     * Récupérer tous les topics d'une catégorie
     */
    public function findByCategory(int $categoryId, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT 
                    ft.*,
                    u.username, u.profile_picture,
                    COUNT(DISTINCT fp.id) as posts_count
                  FROM forum_topics ft
                  INNER JOIN users u ON ft.user_id = u.id
                  LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
                  WHERE ft.category_id = :category_id
                  GROUP BY ft.id
                  ORDER BY ft.is_pinned DESC, ft.updated_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $topics = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $topic = new ForumTopic($row);
            $topic->user = new User($row);
            $topic->posts_count = (int)$row['posts_count'];
            $topic->last_post = $this->getLastPostForTopic($topic->id);
            
            $topics[] = $topic;
        }
        
        return $topics;
    }
    
    /**
     * Créer un nouveau topic
     */
    public function create(int $categoryId, int $userId, string $title, string $firstPostContent): ?ForumTopic {
        try {
            $this->conn->beginTransaction();
            
            // Créer le topic
            $query = "INSERT INTO forum_topics (category_id, user_id, title) 
                      VALUES (:category_id, :user_id, :title)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->execute();
            
            $topicId = $this->conn->lastInsertId();
            
            // Créer le premier post
            $postQuery = "INSERT INTO forum_posts (topic_id, user_id, content) 
                          VALUES (:topic_id, :user_id, :content)";
            $postStmt = $this->conn->prepare($postQuery);
            $postStmt->bindParam(':topic_id', $topicId, PDO::PARAM_INT);
            $postStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $postStmt->bindParam(':content', $firstPostContent, PDO::PARAM_STR);
            $postStmt->execute();
            
            $this->conn->commit();
            
            return $this->findById($topicId);
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creating topic: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trouver un topic par ID
     */
    public function findById(int $id): ?ForumTopic {
        $query = "SELECT ft.*, u.username, u.profile_picture, fc.name as category_name
                  FROM forum_topics ft
                  INNER JOIN users u ON ft.user_id = u.id
                  INNER JOIN forum_categories fc ON ft.category_id = fc.id
                  WHERE ft.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $topic = new ForumTopic($row);
            $topic->user = new User($row);
            $topic->category = new ForumCategory(['id' => $row['category_id'], 'name' => $row['category_name']]);
            return $topic;
        }
        
        return null;
    }
    
    /**
     * Incrémenter le nombre de vues
     */
    public function incrementViews(int $id): void {
        $query = "UPDATE forum_topics SET views_count = views_count + 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    /**
     * Compter les topics d'une catégorie
     */
    public function countByCategory(int $categoryId): int {
        $query = "SELECT COUNT(*) as count FROM forum_topics WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
    
    /**
     * Récupérer le dernier post d'un topic
     */
    private function getLastPostForTopic(int $topicId): ?ForumPost {
        $query = "SELECT fp.*, u.username, u.profile_picture
                  FROM forum_posts fp
                  INNER JOIN users u ON fp.user_id = u.id
                  WHERE fp.topic_id = :topic_id
                  ORDER BY fp.created_at DESC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $topicId, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $post = new ForumPost($row);
            $post->user = new User($row);
            return $post;
        }
        
        return null;
    }
}

/**
 * Repository pour les posts du forum
 */
class ForumPostRepository {
    private $conn;
    
    public function __construct() {
        $this->conn = getDatabase();
    }
    
    /**
     * Récupérer tous les posts d'un topic
     */
    public function findByTopic(int $topicId, int $page = 1, int $perPage = 15, ?int $currentUserId = null): array {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT 
                    fp.*,
                    u.username, u.profile_picture, u.first_name, u.last_name,
                    COUNT(DISTINCT fpl.id) as likes_count";
        
        if ($currentUserId) {
            $query .= ", EXISTS(
                        SELECT 1 FROM forum_post_likes 
                        WHERE post_id = fp.id AND user_id = :current_user_id
                      ) as is_liked_by_current_user";
        }
        
        $query .= " FROM forum_posts fp
                    INNER JOIN users u ON fp.user_id = u.id
                    LEFT JOIN forum_post_likes fpl ON fp.id = fpl.post_id
                    WHERE fp.topic_id = :topic_id
                    GROUP BY fp.id
                    ORDER BY fp.created_at ASC
                    LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $topicId, PDO::PARAM_INT);
        if ($currentUserId) {
            $stmt->bindParam(':current_user_id', $currentUserId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $posts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $post = new ForumPost($row);
            $post->user = new User($row);
            $post->likes_count = (int)$row['likes_count'];
            if ($currentUserId) {
                $post->is_liked_by_current_user = (bool)$row['is_liked_by_current_user'];
            }
            
            $posts[] = $post;
        }
        
        return $posts;
    }
    
    /**
     * Créer un nouveau post
     */
    public function create(int $topicId, int $userId, string $content): ?ForumPost {
        $query = "INSERT INTO forum_posts (topic_id, user_id, content) 
                  VALUES (:topic_id, :user_id, :content)";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':topic_id', $topicId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':content', $content, PDO::PARAM_STR);
            $stmt->execute();
            
            return $this->findById($this->conn->lastInsertId());
        } catch (Exception $e) {
            error_log("Error creating post: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trouver un post par ID
     */
    public function findById(int $id): ?ForumPost {
        $query = "SELECT fp.*, u.username, u.profile_picture
                  FROM forum_posts fp
                  INNER JOIN users u ON fp.user_id = u.id
                  WHERE fp.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $post = new ForumPost($row);
            $post->user = new User($row);
            return $post;
        }
        
        return null;
    }
    
    /**
     * Compter les posts d'un topic
     */
    public function countByTopic(int $topicId): int {
        $query = "SELECT COUNT(*) as count FROM forum_posts WHERE topic_id = :topic_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':topic_id', $topicId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
    
    /**
     * Liker un post
     */
    public function toggleLike(int $postId, int $userId): bool {
        try {
            // Vérifier si le like existe déjà
            $checkQuery = "SELECT id FROM forum_post_likes 
                          WHERE post_id = :post_id AND user_id = :user_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                // Unlike
                $deleteQuery = "DELETE FROM forum_post_likes 
                               WHERE post_id = :post_id AND user_id = :user_id";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
                $deleteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                return $deleteStmt->execute();
            } else {
                // Like
                $insertQuery = "INSERT INTO forum_post_likes (post_id, user_id) 
                               VALUES (:post_id, :user_id)";
                $insertStmt = $this->conn->prepare($insertQuery);
                $insertStmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
                $insertStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                return $insertStmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error toggling like: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Repository pour gérer les sessions de lecture
 */
class ReadingSessionRepository extends BaseRepository {
    
    public function findOrCreateSession(int $userId, int $bookId): ReadingSession {
        $stmt = $this->prepare("
            SELECT * FROM user_library 
            WHERE user_id = :user_id AND book_id = :book_id
        ");
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return new ReadingSession($data);
        }
        
        // Créer une nouvelle session
        $stmt = $this->prepare("
            INSERT INTO user_library (user_id, book_id, last_page_read, added_at) 
            VALUES (:user_id, :book_id, 1, NOW())
        ");
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();
        
        return new ReadingSession([
            'id' => $this->lastInsertId(),
            'user_id' => $userId,
            'book_id' => $bookId,
            'current_page' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function updateProgress(ReadingSession $session): void {
        $stmt = $this->prepare("
            UPDATE user_library 
            SET last_page_read = :page,
                last_access = NOW()
            WHERE user_id = :user_id 
            AND book_id = :book_id
        ");
        
        $stmt->bindValue(':page', $session->getCurrentPage(), PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $session->getUserId(), PDO::PARAM_INT);
        $stmt->bindValue(':book_id', $session->getBookId(), PDO::PARAM_INT);
        $stmt->execute();
    }
    
    public function getBookmarks(int $userId, int $bookId): array {
        $stmt = $this->prepare("
            SELECT * FROM bookmarks 
            WHERE user_id = :user_id 
            AND book_id = :book_id 
            ORDER BY page_number ASC
        ");
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Mapper les noms de colonnes pour la compatibilité
        $bookmarks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bookmarks[] = [
                'id' => $row['id'],
                'page' => $row['page_number'],
                'title' => $row['bookmark_name'] ?? '',
                'created_at' => $row['created_at']
            ];
        }
        
        return $bookmarks;
    }
    
    public function saveBookmark(int $userId, int $bookId, int $page, string $title = ''): bool {
        try {
            $stmt = $this->prepare("
                INSERT INTO bookmarks (user_id, book_id, page_number, bookmark_name, created_at)
                VALUES (:user_id, :book_id, :page_number, :bookmark_name, NOW())
            ");
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':page_number', $page, PDO::PARAM_INT);
            $stmt->bindParam(':bookmark_name', $title, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error saving bookmark: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Repository pour gérer les documents PDF
 */
class PDFRepository extends BaseRepository {
    
    public function validatePath(string $path): bool {
        if (empty($path)) {
            return false;
        }
        
        $document = new PDFDocument($path);
        return $document->isValid();
    }
    
    public function resolvePath(string $originalPath): string {
        $document = new PDFDocument($originalPath);
        return $document->getFilePath();
    }
    
    public function getMetadata(string $path): array {
        $document = new PDFDocument($path);
        if (!$document->loadMetadata()) {
            throw new Exception($document->getLastError() ?? "Erreur lors du chargement des métadonnées");
        }
        return $document->getMetadata();
    }
}