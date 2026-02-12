<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create post table for CMS system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE post (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255) DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            paragraph LONGTEXT DEFAULT NULL,
            layout VARCHAR(50) NOT NULL,
            is_main TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE post');
    }
}
