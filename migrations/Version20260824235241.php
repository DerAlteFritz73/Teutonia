<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824235241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add post_image table for attaching several photos to a single post';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE post_image (
            id INT AUTO_INCREMENT NOT NULL,
            post_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            rotation INT DEFAULT 0,
            position INT DEFAULT 0,
            INDEX idx_post_image_post (post_id),
            PRIMARY KEY (id),
            CONSTRAINT fk_post_image_post FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE post_image');
    }
}
