<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401213824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE song_link (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(500) NOT NULL, label VARCHAR(255) DEFAULT NULL, song_id INT NOT NULL, INDEX IDX_453F4F04A0BDB2F3 (song_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE song_link ADD CONSTRAINT FK_453F4F04A0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE song_suggestion ADD link VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD login_count INT NOT NULL, ADD last_login_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_link DROP FOREIGN KEY FK_453F4F04A0BDB2F3');
        $this->addSql('DROP TABLE song_link');
        $this->addSql('ALTER TABLE song_suggestion DROP link');
        $this->addSql('ALTER TABLE `user` DROP login_count, DROP last_login_at');
    }
}
