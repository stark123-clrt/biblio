<?php

//CLASSE 1


class User
{
    private $id;
    private $username;
    private $email;
    private $password;
    private $firstName;
    private $lastName;
    private $profilePicture;
    private $role;
    private $isActive;
    private $lastLogin;
    private $createdAt;
    private $updatedAt;
    private $emailVerifiedAt;
    private $emailVerificationToken;
    private $passwordResetToken;
    private $passwordResetExpires;
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    public function toArray(): array
    {
        return [
        'id' => $this->id,
        'username' => $this->username,
        'email' => $this->email,
        'password' => $this->password,
        'first_name' => $this->firstName,
        'last_name' => $this->lastName,
        'profile_picture' => $this->profilePicture,
        'role' => $this->role,
        'is_active' => $this->isActive,
        'last_login' => $this->lastLogin,
        'created_at' => $this->createdAt,
        'updated_at' => $this->updatedAt,
        'email_verified_at' => $this->emailVerifiedAt,
        'email_verification_token' => $this->emailVerificationToken,
        'password_reset_token' => $this->passwordResetToken,
        'password_reset_expires' => $this->passwordResetExpires

        ];
    }
    // ===== GETTERS =====
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUsername(): ?string
    {
        return $this->username;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }
    public function getLastName(): ?string
    {
        return $this->lastName;
    }
    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }
    public function getRole(): ?string
    {
        return $this->role;
    }
    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }
    public function getLastLogin(): ?string
    {
        return $this->lastLogin;
    }
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    //mail
    public function getEmailVerifiedAt(): ?string
    {
        return $this->emailVerifiedAt;
    }
    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    // recup-passe
    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }
    public function getPasswordResetExpires(): ?string
    {
        return $this->passwordResetExpires;
    }


    // ===== SETTERS =====
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }
    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }
    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }
    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }
    public function setRole(?string $role): self
    {
        $this->role = $role;
        return $this;
    }
    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }
    public function setLastLogin(?string $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    //mail
    public function setEmailVerifiedAt(?string $emailVerifiedAt): self
    {

        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }
    public function setEmailVerificationToken(?string $emailVerificationToken): self
    {

        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }


    //recup pass

    public function setPasswordResetToken(?string $passwordResetToken): self
    {

        $this->passwordResetToken = $passwordResetToken;
        return $this;
    }
    public function setPasswordResetExpires(?string $passwordResetExpires): self
    {

        $this->passwordResetExpires = $passwordResetExpires;
        return $this;
    }

    // ===== MÉTHODES MÉTIER =====

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName) ?: $this->username;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActiveUser(): bool
    {
        return $this->isActive === true;
    }


    public function isActive(): bool
    {
        return $this->isActive === true;
    }



    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }


    public function setNewPassword(string $password): self
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        return $this;
    }

    public function getInitials(): string
    {
        $firstInitial = $this->firstName ? strtoupper($this->firstName[0]) : '';
        $lastInitial = $this->lastName ? strtoupper($this->lastName[0]) : '';
        if ($firstInitial && $lastInitial) {
            return $firstInitial . $lastInitial;
        }

        return strtoupper(substr($this->username, 0, 2));
    }

    public function hasProfilePicture(): bool
    {
        return !empty($this->profilePicture);
    }



    /**
     * Vérifier si l'email de l'utilisateur est vérifié
     */
    public function isEmailVerified(): bool
    {
        return !empty($this->emailVerifiedAt);
    }

    /**
     * Marquer l'email comme vérifié
     */
    public function markEmailAsVerified(): self
    {
        $this->emailVerifiedAt = date('Y-m-d H:i:s');
        $this->emailVerificationToken = null;
        return $this;
    }

    /**
     * Générer un nouveau token de vérification
     */
    public function generateVerificationToken(): self
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));
        return $this;
    }

    /**
     * Vérifier si un token correspond
     */
    public function verifyEmailToken(string $token): bool
    {
        return hash_equals($this->emailVerificationToken, $token);
    }



    /**
     * Générer un token de récupération de mot de passe
     */
    public function generatePasswordResetToken(): self
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
// Token valide pendant 1 heure
        $this->passwordResetExpires = date('Y-m-d H:i:s', time() + 3600);
        return $this;
    }

    /**
     * Vérifier si le token de récupération est valide
     */
    public function isPasswordResetTokenValid(string $token): bool
    {
        // Vérifier que le token correspond
        if (!hash_equals($this->passwordResetToken, $token)) {
            return false;
        }

        // Vérifier que le token n'a pas expiré
        if (empty($this->passwordResetExpires)) {
            return false;
        }

        $expiryTime = strtotime($this->passwordResetExpires);
        return $expiryTime > time();
    }

    /**
     * Vérifier si un token de récupération a expiré
     */
    public function isPasswordResetTokenExpired(): bool
    {
        if (empty($this->passwordResetExpires)) {
            return true;
        }

        $expiryTime = strtotime($this->passwordResetExpires);
        return $expiryTime <= time();
    }

    /**
     * Nettoyer le token de récupération après utilisation
     */
    public function clearPasswordResetToken(): self
    {
        $this->passwordResetToken = null;
        $this->passwordResetExpires = null;
        return $this;
    }

    /**
     * Obtenir le temps restant avant expiration (en minutes)
     */
    public function getPasswordResetTimeRemaining(): int
    {
        if (empty($this->passwordResetExpires)) {
            return 0;
        }

        $expiryTime = strtotime($this->passwordResetExpires);
        $remainingSeconds = $expiryTime - time();
        return max(0, ceil($remainingSeconds / 60));
    }
}





//CLASSE 2

class Book
{
    private $id;
    private $title;
    private $author;
    private $description;
    private $coverImage;
    private $pdfPath;
    private $pagesCount;
    private $publicationYear;
    private $publisher;
    private $isFeatured;
    private $viewsCount;
    private $createdAt;
    private $updatedAt;
    private $categoryName;
    private $avgRating;
    private $totalReviews;
    private $lastPageRead;
    private $addedAt;
    private $lastReadAt;
    private $isFavoriteInLibrary;
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }



    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }



    public function toArray(): array
    {
        return [
        'id' => $this->id,
        'title' => $this->title,
        'author' => $this->author,
        'description' => $this->description,
        'cover_image' => $this->coverImage,
        'pdf_path' => $this->pdfPath,
        'pages_count' => $this->pagesCount,
        'publication_year' => $this->publicationYear,
        'publisher' => $this->publisher,
        'is_featured' => $this->isFeatured,
        'views_count' => $this->viewsCount,
        'created_at' => $this->createdAt,
        'updated_at' => $this->updatedAt,
        'category_name' => $this->categoryName,
        'avg_rating' => $this->avgRating,
        'total_reviews' => $this->totalReviews,
        'last_page_read' => $this->lastPageRead,
        'added_at' => $this->addedAt,
        'last_read_at' => $this->lastReadAt,
        'is_favorite' => $this->isFavoriteInLibrary
        ];
    }


    // ===== GETTERS =====
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function getAuthor(): ?string
    {
        return $this->author;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }
    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }
    public function getPagesCount(): ?int
    {
        return $this->pagesCount;
    }
    public function getPublicationYear(): ?int
    {
        return $this->publicationYear;
    }
    public function getPublisher(): ?string
    {
        return $this->publisher;
    }
    public function getIsFeatured(): ?bool
    {
        return $this->isFeatured;
    }
    public function getViewsCount(): ?int
    {
        return $this->viewsCount;
    }
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
    public function getCategoryName(): ?string
    {
        return $this->categoryName;
    }
    public function getAvgRating(): ?float
    {
        return $this->avgRating;
    }
    public function getTotalReviews(): ?int
    {
        return $this->totalReviews;
    }
    public function getLastPageRead(): ?int
    {
        return $this->lastPageRead;
    }
    public function getAddedAt(): ?string
    {
        return $this->addedAt;
    }
    public function getLastReadAt(): ?string
    {
        return $this->lastReadAt;
    }
    public function getIsFavoriteInLibrary(): ?bool
    {
        return $this->isFavoriteInLibrary;
    }

    // ===== SETTERS =====
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }
    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    public function setCoverImage(?string $coverImage): self
    {
        $this->coverImage = $coverImage;
        return $this;
    }
    public function setPdfPath(?string $pdfPath): self
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }
    public function setPagesCount(?int $pagesCount): self
    {
        $this->pagesCount = $pagesCount;
        return $this;
    }
    public function setPublicationYear(?int $publicationYear): self
    {
        $this->publicationYear = $publicationYear;
        return $this;
    }
    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }
    public function setIsFeatured(?bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }
    public function setViewsCount(?int $viewsCount): self
    {
        $this->viewsCount = $viewsCount;
        return $this;
    }
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function setCategoryName(?string $categoryName): self
    {
        $this->categoryName = $categoryName;
        return $this;
    }
    public function setAvgRating(?float $avgRating): self
    {
        $this->avgRating = $avgRating;
        return $this;
    }
    public function setTotalReviews(?int $totalReviews): self
    {
        $this->totalReviews = $totalReviews;
        return $this;
    }
    public function setLastPageRead(?int $lastPageRead): self
    {
        $this->lastPageRead = $lastPageRead;
        return $this;
    }
    public function setAddedAt(?string $addedAt): self
    {
        $this->addedAt = $addedAt;
        return $this;
    }
    public function setLastReadAt(?string $lastReadAt): self
    {
        $this->lastReadAt = $lastReadAt;
        return $this;
    }
    public function setIsFavoriteInLibrary(?bool $isFavoriteInLibrary): self
    {
        $this->isFavoriteInLibrary = $isFavoriteInLibrary;
        return $this;
    }

    // ===== MÉTHODES MÉTIER =====

    public function isFeaturedBook(): bool
    {
        return $this->isFeatured === true;
    }




    public function incrementViews(): self
    {
        $this->viewsCount = ($this->viewsCount ?? 0) + 1;
        return $this;
    }




    public function getShortDescription(int $length = 150): string
    {
        if (!$this->description) {
            return 'Aucune description disponible.';
        }

        if (strlen($this->description) <= $length) {
            return $this->description;
        }

        return substr($this->description, 0, $length) . '...';
    }



    public function hasCover(): bool
    {
        return !empty($this->coverImage);
    }



    public function hasPdf(): bool
    {
        return !empty($this->pdfPath);
    }



    public function getStarsArray(): array
    {
        $rating = $this->avgRating ?? 0;
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[] = $i <= round($rating);
        }
        return $stars;
    }


    public function getFormattedViews(): string
    {
        $views = $this->viewsCount ?? 0;
        if ($views >= 1000) {
            return number_format($views / 1000, 1) . 'k';
        }
        return (string)$views;
    }



    public function getCoverImagePath(): string
    {
        if (!$this->coverImage) {
            return '';
        }

        // Nettoyer le chemin si nécessaire
        $path = $this->coverImage;
        if (strpos($path, '../') === 0) {
            $path = substr($path, 3);
        }

        return $path;
    }
}





//CLASSE3

class Category
{
    private $id;
    private $name;
    private $description;
    private $color;
    private $icon;
    private $createdAt;
// Propriétés pour les jointures
    private $bookCount;
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }



    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'created_at' => $this->createdAt,
            'book_count' => $this->bookCount
        ];
    }

    // ===== GETTERS =====
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function getColor(): ?string
    {
        return $this->color;
    }
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    public function getBookCount(): ?int
    {
        return $this->bookCount;
    }

    // ===== SETTERS =====
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }
    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function setBookCount(?int $bookCount): self
    {
        $this->bookCount = $bookCount;
        return $this;
    }

    // ===== MÉTHODES MÉTIER =====

    public function hasBooks(): bool
    {
        return ($this->bookCount ?? 0) > 0;
    }

    public function getFormattedBookCount(): string
    {
        $count = $this->bookCount ?? 0;
        return $count . ' livre' . ($count > 1 ? 's' : '');
    }
}



//CLASSE 4

class Note
{
    private $id;
    private $userId;
    private $bookId;
    private $noteText;
    private $pageNumber;
    private $isPublic;
    private $createdAt;
    private $updatedAt;
// Propriétés pour les jointures
    private $bookTitle;
    private $bookCoverImage;
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'book_id' => $this->bookId,
            'note_text' => $this->noteText,
            'page_number' => $this->pageNumber,
            'is_public' => $this->isPublic,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'book_title' => $this->bookTitle,
            'book_cover_image' => $this->bookCoverImage
        ];
    }
    // ===== GETTERS =====
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    public function getBookId(): ?int
    {
        return $this->bookId;
    }
    public function getNoteText(): ?string
    {
        return $this->noteText;
    }
    public function getPageNumber(): ?int
    {
        return $this->pageNumber;
    }
    public function getIsPublic(): ?bool
    {
        return $this->isPublic;
    }
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
    public function getBookTitle(): ?string
    {
        return $this->bookTitle;
    }
    public function getBookCoverImage(): ?string
    {
        return $this->bookCoverImage;
    }

    // ===== SETTERS =====
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    public function setBookId(?int $bookId): self
    {
        $this->bookId = $bookId;
        return $this;
    }
    public function setNoteText(?string $noteText): self
    {
        $this->noteText = $noteText;
        return $this;
    }
    public function setPageNumber(?int $pageNumber): self
    {
        $this->pageNumber = $pageNumber;
        return $this;
    }
    public function setIsPublic(?bool $isPublic): self
    {
        $this->isPublic = $isPublic;
        return $this;
    }
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function setBookTitle(?string $bookTitle): self
    {
        $this->bookTitle = $bookTitle;
        return $this;
    }
    public function setBookCoverImage(?string $bookCoverImage): self
    {
        $this->bookCoverImage = $bookCoverImage;
        return $this;
    }

    // ===== MÉTHODES MÉTIER =====

    public function getShortText(int $length = 100): string
    {
        if (!$this->noteText) {
            return '';
        }

        if (strlen($this->noteText) <= $length) {
            return $this->noteText;
        }

        return substr($this->noteText, 0, $length) . '...';
    }

    public function isPublicNote(): bool
    {
        return $this->isPublic === true;
    }

    public function getFormattedDate(): string
    {
        if (!$this->createdAt) {
            return '';
        }

        $date = new DateTime($this->createdAt);
        return $date->format('d/m/Y à H:i');
    }

    public function getTimeAgo(): string
    {
        if (!$this->createdAt) {
            return '';
        }

        $date = new DateTime($this->createdAt);
        $now = new DateTime();
        $diff = $now->diff($date);
        if ($diff->d > 0) {
            return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        } else {
            return 'À l\'instant';
        }
    }
}









//CLASSE 5

class Comment
{
    private $id;
    private $userId;
    private $bookId;
    private $rating;
    private $commentText;
    private $isValidated;
    private $createdAt;
    private $updatedAt;
// Propriétés supplémentaires pour les jointures
    private $username;
    private $bookTitle;
    private $coverImage;
    private $userProfilePicture;
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'book_id' => $this->bookId,
            'rating' => $this->rating,
            'comment_text' => $this->commentText,
            'is_validated' => $this->isValidated,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'username' => $this->username,
            'book_title' => $this->bookTitle,
            'cover_image' => $this->coverImage,
            'user_profile_picture' => $this->userProfilePicture
        ];
    }

    // ===== GETTERS =====
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    public function getBookId(): ?int
    {
        return $this->bookId;
    }
    public function getRating(): ?int
    {
        return $this->rating;
    }
    public function getCommentText(): ?string
    {
        return $this->commentText;
    }
    public function getIsValidated(): ?bool
    {
        return $this->isValidated;
    }
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
    public function getUsername(): ?string
    {
        return $this->username;
    }
    public function getBookTitle(): ?string
    {
        return $this->bookTitle;
    }
    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }
    public function getUserProfilePicture(): ?string
    {
        return $this->userProfilePicture;
    }

    // ===== SETTERS =====
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    public function setBookId(?int $bookId): self
    {
        $this->bookId = $bookId;
        return $this;
    }
    public function setRating(?int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }
    public function setCommentText(?string $commentText): self
    {
        $this->commentText = $commentText;
        return $this;
    }
    public function setIsValidated(?bool $isValidated): self
    {
        $this->isValidated = $isValidated;
        return $this;
    }
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }
    public function setBookTitle(?string $bookTitle): self
    {
        $this->bookTitle = $bookTitle;
        return $this;
    }
    public function setCoverImage(?string $coverImage): self
    {
        $this->coverImage = $coverImage;
        return $this;
    }
    public function setUserProfilePicture(?string $userProfilePicture): self
    {
        $this->userProfilePicture = $userProfilePicture;
        return $this;
    }

    // ===== MÉTHODES MÉTIER =====

    public function isValidated(): bool
    {
        return $this->isValidated === true;
    }

    public function getShortComment(int $length = 100): string
    {
        if (!$this->commentText) {
            return '';
        }

        if (strlen($this->commentText) <= $length) {
            return $this->commentText;
        }

        return substr($this->commentText, 0, $length) . '...';
    }

    public function getStarsArray(): array
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[] = $i <= ($this->rating ?? 0);
        }
        return $stars;
    }

    public function getFormattedDate(): string
    {
        if (!$this->createdAt) {
            return '';
        }

        $date = new DateTime($this->createdAt);
        return $date->format('d/m/Y à H:i');
    }

    public function getTimeAgo(): string
    {
        if (!$this->createdAt) {
            return '';
        }

        $date = new DateTime($this->createdAt);
        $now = new DateTime();
        $diff = $now->diff($date);
        if ($diff->d > 0) {
            return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        } else {
            return 'À l\'instant';
        }
    }
}












class ReadingHistory
{
    private $id;
    private $userId;
    private $bookId;
    private $action;
    private $pageNumber;
    private $timestamp;
    private $bookTitle;
    private $bookCoverImage;
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    public function getBookId(): ?int
    {
        return $this->bookId;
    }
    public function getAction(): ?string
    {
        return $this->action;
    }
    public function getPageNumber(): ?int
    {
        return $this->pageNumber;
    }
    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }
    public function getBookTitle(): ?string
    {
        return $this->bookTitle;
    }
    public function getBookCoverImage(): ?string
    {
        return $this->bookCoverImage;
    }

    // Setters
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    public function setBookId(?int $bookId): self
    {
        $this->bookId = $bookId;
        return $this;
    }
    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }
    public function setPageNumber(?int $pageNumber): self
    {
        $this->pageNumber = $pageNumber;
        return $this;
    }
    public function setTimestamp(?string $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }
    public function setBookTitle(?string $bookTitle): self
    {
        $this->bookTitle = $bookTitle;
        return $this;
    }
    public function setBookCoverImage(?string $bookCoverImage): self
    {
        $this->bookCoverImage = $bookCoverImage;
        return $this;
    }

    // Méthodes métier
    public function getFormattedDate(): string
    {
        if (!$this->timestamp) {
            return '';
        }
        $date = new DateTime($this->timestamp);
        return $date->format('d/m/Y à H:i');
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'started' => 'Vous avez commencé à lire',
            'continued' => 'Vous avez continué à lire',
            'finished' => 'Vous avez terminé',
            default => 'Vous avez lu'
        };
    }

    public function getActionIcon(): string
    {
        return match ($this->action) {
            'started' => 'fas fa-play',
            'continued' => 'fas fa-book-reader',
            'finished' => 'fas fa-check',
            default => 'fas fa-book'
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'started' => 'from-green-500 to-green-600',
            'continued' => 'from-blue-500 to-blue-600',
            'finished' => 'from-purple-500 to-purple-600',
            default => 'from-gray-500 to-gray-600'
        };
    }
}
