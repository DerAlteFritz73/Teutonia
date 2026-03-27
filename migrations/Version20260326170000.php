<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add song_link table for per-song URLs (YouTube, etc.)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE song_link (
            id INT AUTO_INCREMENT NOT NULL,
            song_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            label VARCHAR(255) DEFAULT NULL,
            INDEX IDX_song_link_song_id (song_id),
            CONSTRAINT FK_song_link_song_id FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE song_link');
    }
}
