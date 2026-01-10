<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveForumTables extends AbstractMigration
{
    /**
     * Suppression des tables du forum
     * Les tables seront supprimées dans l'ordre inverse des dépendances
     */
    public function change(): void
    {
        // Supprimer dans l'ordre inverse des dépendances
        
        // 1. Supprimer forum_post_likes (dépend de forum_posts)
        if ($this->hasTable('forum_post_likes')) {
            $this->table('forum_post_likes')->drop()->save();
        }
        
        // 2. Supprimer forum_posts (dépend de forum_topics)
        if ($this->hasTable('forum_posts')) {
            $this->table('forum_posts')->drop()->save();
        }
        
        // 3. Supprimer forum_topics (dépend de forum_categories)
        if ($this->hasTable('forum_topics')) {
            $this->table('forum_topics')->drop()->save();
        }
        
        // 4. Supprimer forum_categories (table principale)
        if ($this->hasTable('forum_categories')) {
            $this->table('forum_categories')->drop()->save();
        }
    }
}
