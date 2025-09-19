<?php  
// AuthenticationTest.php - Tests d'authentification

// Inclure directement avec le chemin complet
require_once __DIR__ . '/../classes/Core.php';
require_once __DIR__ . '/../classes/Models.php';
require_once __DIR__ . '/../classes/Repositories.php';

use PHPUnit\Framework\TestCase;

class AuthenticationTest extends TestCase  
{
    public function testPasswordHashing() {
        $user = new User(['username' => 'testuser']);
        
        if (method_exists($user, 'setNewPassword') && method_exists($user, 'verifyPassword')) {
            $user->setNewPassword('motdepasse123');
            
            // Test mot de passe correct
            $this->assertTrue($user->verifyPassword('motdepasse123'));
            
            // Test mot de passe incorrect
            $this->assertFalse($user->verifyPassword('mauvaismdp'));
            $this->assertFalse($user->verifyPassword(''));
            $this->assertFalse($user->verifyPassword('123'));
        } else {
            $this->markTestSkipped('Méthodes de mot de passe non disponibles');
        }
    }
    
    public function testUserRoleAuthentication() {
        $admin = new User(['role' => 'admin']);
        $user = new User(['role' => 'user']);
        $moderator = new User(['role' => 'moderator']);
        $nullRole = new User(['role' => null]);
        
        // Test rôle admin
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($moderator->isAdmin());
        $this->assertFalse($nullRole->isAdmin());
    }
    
    public function testUserActiveStatus() {
        $activeUser = new User(['is_active' => true]);
        $inactiveUser = new User(['is_active' => false]);
        $nullActive = new User(['is_active' => null]);
        
        if (method_exists($activeUser, 'isActiveUser')) {
            $this->assertTrue($activeUser->isActiveUser());
            $this->assertFalse($inactiveUser->isActiveUser());
            $this->assertFalse($nullActive->isActiveUser());
        } else {
            // Test alternatif avec getIsActive
            $this->assertTrue($activeUser->getIsActive());
            $this->assertFalse($inactiveUser->getIsActive());
            $this->assertNull($nullActive->getIsActive());
        }
    }
    
    public function testEmailVerification() {
        $verifiedUser = new User(['email_verified_at' => '2023-01-01 12:00:00']);
        $unverifiedUser = new User(['email_verified_at' => null]);
        
        if (method_exists($verifiedUser, 'isEmailVerified')) {
            $this->assertTrue($verifiedUser->isEmailVerified());
            $this->assertFalse($unverifiedUser->isEmailVerified());
        } else {
            $this->markTestSkipped('Méthode isEmailVerified non disponible');
        }
    }
    
    public function testTokenGeneration() {
        $user = new User();
        
        if (method_exists($user, 'generateVerificationToken')) {
            $user->generateVerificationToken();
            
            $token = $user->getEmailVerificationToken();
            $this->assertNotNull($token);
            $this->assertIsString($token);
            $this->assertGreaterThan(10, strlen($token)); // Token assez long
        } else {
            $this->markTestSkipped('Méthode generateVerificationToken non disponible');
        }
    }
    
    public function testPasswordResetToken() {
        $user = new User();
        
        if (method_exists($user, 'generatePasswordResetToken')) {
            $user->generatePasswordResetToken();
            
            $token = $user->getPasswordResetToken();
            $expires = $user->getPasswordResetExpires();
            
            $this->assertNotNull($token);
            $this->assertNotNull($expires);
            $this->assertIsString($token);
            $this->assertGreaterThan(20, strlen($token));
        } else {
            $this->markTestSkipped('Méthode generatePasswordResetToken non disponible');
        }
    }
    
    public function testPasswordResetTokenValidation() {
        $user = new User();
        
        if (method_exists($user, 'generatePasswordResetToken') && 
            method_exists($user, 'isPasswordResetTokenValid')) {
            
            $user->generatePasswordResetToken();
            $token = $user->getPasswordResetToken();
            
            // Token valide
            $this->assertTrue($user->isPasswordResetTokenValid($token));
            
            // Token invalide
            $this->assertFalse($user->isPasswordResetTokenValid('token_invalide'));
            $this->assertFalse($user->isPasswordResetTokenValid(''));
        } else {
            $this->markTestSkipped('Méthodes de validation de token non disponibles');
        }
    }
    
    public function testUserCredentialsValidation() {
        // Test email valide
        $validUser = new User(['email' => 'user@example.com']);
        $this->assertEquals('user@example.com', $validUser->getEmail());
        
        // Test username valide
        $this->assertEquals('user@example.com', $validUser->getEmail());
        
        // Test avec données vides
        $emptyUser = new User(['username' => '', 'email' => '']);
        $this->assertEquals('', $emptyUser->getUsername());
        $this->assertEquals('', $emptyUser->getEmail());
    }
    
    public function testSessionSecurity() {
        // Test de création d'un utilisateur pour session
        $sessionUser = new User([
            'id' => 123,
            'username' => 'sessionuser',
            'email' => 'session@test.com',
            'role' => 'user',
            'is_active' => true,
            'email_verified_at' => '2023-01-01 12:00:00'
        ]);
        
        // Vérifications de sécurité de base
        $this->assertNotNull($sessionUser->getId());
        $this->assertIsInt($sessionUser->getId());
        $this->assertTrue($sessionUser->getIsActive());
        $this->assertFalse($sessionUser->isAdmin());
        
        // Test avec admin
        $adminUser = new User(['role' => 'admin', 'is_active' => true]);
        $this->assertTrue($adminUser->isAdmin());
        $this->assertTrue($adminUser->getIsActive());
    }
    
    public function testLoginAttemptSecurity() {
        if (method_exists(new User(), 'setNewPassword')) {
            $user = new User(['username' => 'securitytest']);
            $user->setNewPassword('password123');
            
            // Tentatives de login avec différents mots de passe
            $attempts = [
                'password123' => true,  // Correct
                'Password123' => false, // Casse différente
                'password' => false,    // Incomplet
                '123' => false,         // Trop court
                '' => false,            // Vide
                'password1234' => false // Trop long
            ];
            
            foreach ($attempts as $password => $expected) {
                $result = $user->verifyPassword($password);
                $this->assertEquals($expected, $result, "Échec pour le mot de passe: '$password'");
            }
        } else {
            $this->markTestSkipped('Méthodes de mot de passe non disponibles');
        }
    }
    
    public function testUserProfileSecurity() {
        // Test des informations sensibles
        $user = new User([
            'username' => 'testuser',
            'email' => 'test@test.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'profile_picture' => '/uploads/user.jpg'
        ]);
        
        // Conversion en array
        $userArray = $user->toArray();
        
        $this->assertArrayHasKey('username', $userArray);
        $this->assertArrayHasKey('email', $userArray);
        
        // Vérifier les informations publiques
        $this->assertEquals('Test User', $user->getFullName());
        
        if (method_exists($user, 'hasProfilePicture')) {
            $this->assertTrue($user->hasProfilePicture());
        }
        
        // Test simple: vérifier que le mot de passe hashé est correct s'il existe
        if (array_key_exists('password', $userArray) && !empty($userArray['password'])) {
            $this->assertIsString($userArray['password']);
        }
    }
    
    public function testAuthenticationFlow() {
        // Simulation d'un flow d'authentification complet
        $userData = [
            'username' => 'flowtest',
            'email' => 'flow@test.com',
            'is_active' => true,
            'email_verified_at' => null
        ];
        
        $user = new User($userData);
        
        // 1. Utilisateur créé mais email non vérifié
        if (method_exists($user, 'isEmailVerified')) {
            $this->assertFalse($user->isEmailVerified());
        }
        
        // 2. Générer token de vérification
        if (method_exists($user, 'generateVerificationToken')) {
            $user->generateVerificationToken();
            $this->assertNotNull($user->getEmailVerificationToken());
        }
        
        // 3. Marquer email comme vérifié
        if (method_exists($user, 'markEmailAsVerified')) {
            $user->markEmailAsVerified();
            $this->assertTrue($user->isEmailVerified());
        }
        
        // 4. Vérifier que l'utilisateur peut se connecter
        $this->assertTrue($user->getIsActive());
        $this->assertEquals('flowtest', $user->getUsername());
    }
}
?>