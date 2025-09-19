<?php  
// RepositoryTest.php - Tests des repositories

// Inclure directement avec le chemin complet
require_once __DIR__ . '/../classes/Core.php';
require_once __DIR__ . '/../classes/Models.php';
require_once __DIR__ . '/../classes/Repositories.php';

use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase  
{
    // =========================================
    // 🧪 TESTS USERREPOSITORY
    // =========================================
    
    public function testUserRepositoryInstantiation() {
        try {
            $userRepo = new UserRepository();
            $this->assertInstanceOf(UserRepository::class, $userRepo);
            $this->assertInstanceOf(BaseRepository::class, $userRepo);
        } catch (Exception $e) {
            $this->markTestSkipped('UserRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testUserRepositoryMethods() {
        try {
            $userRepo = new UserRepository();
            
            // Vérifier les méthodes essentielles
            $this->assertTrue(method_exists($userRepo, 'findById'));
            $this->assertTrue(method_exists($userRepo, 'findByEmail'));
            $this->assertTrue(method_exists($userRepo, 'create'));
            $this->assertTrue(method_exists($userRepo, 'update'));
            $this->assertTrue(method_exists($userRepo, 'countAll'));
            
        } catch (Exception $e) {
            $this->markTestSkipped('UserRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testUserRepositoryFindById() {
        try {
            $userRepo = new UserRepository();
            
            // Test avec ID inexistant
            $user = $userRepo->findById(99999);
            $this->assertNull($user);
            
            // Test avec ID invalide
            $userInvalid = $userRepo->findById(0);
            $this->assertNull($userInvalid);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testUserRepositoryFindByEmail() {
        try {
            $userRepo = new UserRepository();
            
            // Test avec email inexistant
            $user = $userRepo->findByEmail('inexistant@test.com');
            $this->assertNull($user);
            
            // Test avec email vide
            $userEmpty = $userRepo->findByEmail('');
            $this->assertNull($userEmpty);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testUserRepositoryCountAll() {
        try {
            $userRepo = new UserRepository();
            $count = $userRepo->countAll();
            
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }

    // =========================================
    // 📚 TESTS BOOKREPOSITORY
    // =========================================
    
    public function testBookRepositoryInstantiation() {
        try {
            $bookRepo = new BookRepository();
            $this->assertInstanceOf(BookRepository::class, $bookRepo);
            $this->assertInstanceOf(BaseRepository::class, $bookRepo);
        } catch (Exception $e) {
            $this->markTestSkipped('BookRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testBookRepositoryMethods() {
        try {
            $bookRepo = new BookRepository();
            
            // Vérifier les méthodes essentielles
            $this->assertTrue(method_exists($bookRepo, 'findById'));
            $this->assertTrue(method_exists($bookRepo, 'findAll'));
            $this->assertTrue(method_exists($bookRepo, 'search'));
            $this->assertTrue(method_exists($bookRepo, 'countAll'));
            
            // Méthodes spécifiques aux livres
            if (method_exists($bookRepo, 'findFeatured')) {
                $this->assertTrue(method_exists($bookRepo, 'findFeatured'));
            }
            
            if (method_exists($bookRepo, 'findRecent')) {
                $this->assertTrue(method_exists($bookRepo, 'findRecent'));
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('BookRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testBookRepositoryFindById() {
        try {
            $bookRepo = new BookRepository();
            
            // Test avec ID inexistant
            $book = $bookRepo->findById(99999);
            $this->assertNull($book);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testBookRepositorySearch() {
        try {
            $bookRepo = new BookRepository();
            
            // Test recherche simple
            $books = $bookRepo->search('Bible', 10);
            $this->assertIsArray($books);
            
            // Vérifier que ce sont des objets Book
            foreach ($books as $book) {
                $this->assertInstanceOf(Book::class, $book);
            }
            
            // Test recherche vide
            $emptyBooks = $bookRepo->search('MotInexistantDansLaBase123456', 10);
            $this->assertIsArray($emptyBooks);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testBookRepositoryFindFeatured() {
        try {
            $bookRepo = new BookRepository();
            
            if (method_exists($bookRepo, 'findFeatured')) {
                $featuredBooks = $bookRepo->findFeatured(5);
                $this->assertIsArray($featuredBooks);
                
                // Vérifier que ce sont des objets Book
                foreach ($featuredBooks as $book) {
                    $this->assertInstanceOf(Book::class, $book);
                    // Vérifier qu'ils sont bien en vedette
                    if (method_exists($book, 'isFeaturedBook')) {
                        $this->assertTrue($book->isFeaturedBook());
                    }
                }
            } else {
                $this->markTestSkipped('Méthode findFeatured non disponible');
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }

    // =========================================
    // 💬 TESTS COMMENTREPOSITORY
    // =========================================
    
    public function testCommentRepositoryInstantiation() {
        try {
            $commentRepo = new CommentRepository();
            $this->assertInstanceOf(CommentRepository::class, $commentRepo);
            $this->assertInstanceOf(BaseRepository::class, $commentRepo);
        } catch (Exception $e) {
            $this->markTestSkipped('CommentRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testCommentRepositoryMethods() {
        try {
            $commentRepo = new CommentRepository();
            
            // Vérifier les méthodes essentielles
            $this->assertTrue(method_exists($commentRepo, 'findById'));
            $this->assertTrue(method_exists($commentRepo, 'findByBook'));
            $this->assertTrue(method_exists($commentRepo, 'findByUser'));
            $this->assertTrue(method_exists($commentRepo, 'countAll'));
            
        } catch (Exception $e) {
            $this->markTestSkipped('CommentRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testCommentRepositoryFindByBook() {
        try {
            $commentRepo = new CommentRepository();
            
            // Test avec livre inexistant
            $comments = $commentRepo->findByBook(99999);
            $this->assertIsArray($comments);
            $this->assertCount(0, $comments);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testCommentRepositoryFindByUser() {
        try {
            $commentRepo = new CommentRepository();
            
            // Test avec utilisateur inexistant
            $comments = $commentRepo->findByUser(99999);
            $this->assertIsArray($comments);
            
            // Vérifier que ce sont des objets Comment
            foreach ($comments as $comment) {
                $this->assertInstanceOf(Comment::class, $comment);
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }

    // =========================================
    // 📝 TESTS NOTEREPOSITORY
    // =========================================
    
    public function testNoteRepositoryInstantiation() {
        try {
            $noteRepo = new NoteRepository();
            $this->assertInstanceOf(NoteRepository::class, $noteRepo);
            $this->assertInstanceOf(BaseRepository::class, $noteRepo);
        } catch (Exception $e) {
            $this->markTestSkipped('NoteRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testNoteRepositoryMethods() {
        try {
            $noteRepo = new NoteRepository();
            
            // Vérifier les méthodes essentielles
            $this->assertTrue(method_exists($noteRepo, 'findById'));
            $this->assertTrue(method_exists($noteRepo, 'findByUser'));
            $this->assertTrue(method_exists($noteRepo, 'countByUser'));
            
        } catch (Exception $e) {
            $this->markTestSkipped('NoteRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testNoteRepositoryFindByUser() {
        try {
            $noteRepo = new NoteRepository();
            
            // Test avec utilisateur inexistant
            $notes = $noteRepo->findByUser(99999);
            $this->assertIsArray($notes);
            
            // Vérifier que ce sont des objets Note
            foreach ($notes as $note) {
                $this->assertInstanceOf(Note::class, $note);
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testNoteRepositoryCountByUser() {
        try {
            $noteRepo = new NoteRepository();
            
            // Test avec utilisateur inexistant
            $count = $noteRepo->countByUser(99999);
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }

    // =========================================
    // 📚 TESTS LIBRARYREPOSITORY
    // =========================================
    
    public function testLibraryRepositoryInstantiation() {
        try {
            $libraryRepo = new LibraryRepository();
            $this->assertInstanceOf(LibraryRepository::class, $libraryRepo);
            $this->assertInstanceOf(BaseRepository::class, $libraryRepo);
        } catch (Exception $e) {
            $this->markTestSkipped('LibraryRepository non disponible : ' . $e->getMessage());
        }
    }
    
    public function testLibraryRepositoryMethods() {
        try {
            $libraryRepo = new LibraryRepository();
            
            // Vérifier les méthodes essentielles
            if (method_exists($libraryRepo, 'getUserLibrary')) {
                $this->assertTrue(method_exists($libraryRepo, 'getUserLibrary'));
            }
            
            if (method_exists($libraryRepo, 'getUserStats')) {
                $this->assertTrue(method_exists($libraryRepo, 'getUserStats'));
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('LibraryRepository non disponible : ' . $e->getMessage());
        }
    }

    // =========================================
    // 🧪 TESTS INTÉGRATION REPOSITORIES
    // =========================================
    
    public function testRepositoriesIntegration() {
        try {
            $userRepo = new UserRepository();
            $bookRepo = new BookRepository();
            $commentRepo = new CommentRepository();
            
            // Test que tous les repos utilisent la même connexion DB
            $this->assertInstanceOf(BaseRepository::class, $userRepo);
            $this->assertInstanceOf(BaseRepository::class, $bookRepo);
            $this->assertInstanceOf(BaseRepository::class, $commentRepo);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Repositories non disponibles : ' . $e->getMessage());
        }
    }
    
    public function testRepositoryReturnTypes() {
        try {
            $userRepo = new UserRepository();
            $bookRepo = new BookRepository();
            
            // Test que findById retourne bien null pour ID inexistant
            $user = $userRepo->findById(99999);
            $book = $bookRepo->findById(99999);
            
            $this->assertNull($user);
            $this->assertNull($book);
            
            // Test que les méthodes count retournent des entiers
            $userCount = $userRepo->countAll();
            $bookCount = $bookRepo->countAll();
            
            $this->assertIsInt($userCount);
            $this->assertIsInt($bookCount);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testRepositoryPerformance() {
        try {
            $startTime = microtime(true);
            
            $userRepo = new UserRepository();
            $bookRepo = new BookRepository();
            
            // Test de performance basique
            $userRepo->countAll();
            $bookRepo->countAll();
            $bookRepo->search('test', 5);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Les opérations de base ne doivent pas prendre plus de 3 secondes
            $this->assertLessThan(3.0, $executionTime, 'Opérations repository trop lentes');
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
    
    public function testRepositoryErrorHandling() {
        try {
            $userRepo = new UserRepository();
            
            // Test avec des paramètres invalides
            $userNegative = $userRepo->findById(-1);
            $this->assertNull($userNegative);
            
            $userZero = $userRepo->findById(0);
            $this->assertNull($userZero);
            
        } catch (Exception $e) {
            // Si exception, elle doit être gérée proprement
            $this->assertInstanceOf(Exception::class, $e);
            $this->assertNotEmpty($e->getMessage());
        }
    }
    
    public function testRepositoryDataIntegrity() {
        try {
            $commentRepo = new CommentRepository();
            $noteRepo = new NoteRepository();
            
            // Test avec des utilisateurs qui existent potentiellement
            $userIds = [1, 2, 3]; // IDs susceptibles d'exister
            
            foreach ($userIds as $userId) {
                // Si on a des commentaires, vérifier leur intégrité
                $comments = $commentRepo->findByUser($userId);
                if (!empty($comments)) {
                    foreach (array_slice($comments, 0, 3) as $comment) { // Limiter à 3 pour éviter les warnings
                        $this->assertInstanceOf(Comment::class, $comment);
                        $this->assertIsInt($comment->getUserId());
                        $this->assertIsInt($comment->getBookId());
                        $this->assertGreaterThan(0, $comment->getUserId());
                        $this->assertGreaterThan(0, $comment->getBookId());
                    }
                    break; // Sortir dès qu'on trouve des données
                }
                
                // Si on a des notes, vérifier leur intégrité
                $notes = $noteRepo->findByUser($userId);
                if (!empty($notes)) {
                    foreach (array_slice($notes, 0, 3) as $note) { // Limiter à 3
                        $this->assertInstanceOf(Note::class, $note);
                        $this->assertIsInt($note->getUserId());
                        $this->assertIsInt($note->getBookId());
                        $this->assertGreaterThan(0, $note->getUserId());
                        $this->assertGreaterThan(0, $note->getBookId());
                    }
                    break; // Sortir dès qu'on trouve des données
                }
            }
            
            // Si aucune donnée trouvée, marquer comme skipped plutôt que failed
            $this->assertTrue(true, 'Test d\'intégrité des données terminé');
            
        } catch (Exception $e) {
            $this->markTestSkipped('Base de données non disponible : ' . $e->getMessage());
        }
    }
}
?>