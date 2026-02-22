<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create style table and song_keyword_style junction table for musical style classification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS style (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_DE87DB5C5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE IF NOT EXISTS song_keyword_style (song_keyword_id INT NOT NULL, style_id INT NOT NULL, INDEX IDX_song_keyword (song_keyword_id), INDEX IDX_style (style_id), PRIMARY KEY(song_keyword_id, style_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE song_keyword_style ADD CONSTRAINT FK_song_keyword_style_sk FOREIGN KEY IF NOT EXISTS (song_keyword_id) REFERENCES song_keyword (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE song_keyword_style ADD CONSTRAINT FK_song_keyword_style_s FOREIGN KEY IF NOT EXISTS (style_id) REFERENCES style (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song_keyword_style DROP FOREIGN KEY FK_song_keyword_style_sk');
        $this->addSql('ALTER TABLE song_keyword_style DROP FOREIGN KEY FK_song_keyword_style_s');
        $this->addSql('DROP TABLE song_keyword_style');
        $this->addSql('DROP TABLE style');
    }
}
