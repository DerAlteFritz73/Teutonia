<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216215226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE song_suggestion (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, artist VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, pdf_path VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, submitted_by_id INT NOT NULL, INDEX IDX_6728690B79F7D87D (submitted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE song_suggestion_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, suggestion_id INT NOT NULL, INDEX IDX_DC5A5308A76ED395 (user_id), INDEX IDX_DC5A5308A41BB822 (suggestion_id), UNIQUE INDEX UNIQ_user_suggestion (user_id, suggestion_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE song_suggestion ADD CONSTRAINT FK_6728690B79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE song_suggestion_like ADD CONSTRAINT FK_DC5A5308A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE song_suggestion_like ADD CONSTRAINT FK_DC5A5308A41BB822 FOREIGN KEY (suggestion_id) REFERENCES song_suggestion (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_suggestion DROP FOREIGN KEY FK_6728690B79F7D87D');
        $this->addSql('ALTER TABLE song_suggestion_like DROP FOREIGN KEY FK_DC5A5308A76ED395');
        $this->addSql('ALTER TABLE song_suggestion_like DROP FOREIGN KEY FK_DC5A5308A41BB822');
        $this->addSql('DROP TABLE song_suggestion');
        $this->addSql('DROP TABLE song_suggestion_like');
    }
}
