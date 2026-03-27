<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327065514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_link RENAME INDEX idx_song_link_song_id TO IDX_453F4F04A0BDB2F3');
        $this->addSql('ALTER TABLE song_suggestion ADD link VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_link RENAME INDEX idx_453f4f04a0bdb2f3 TO IDX_song_link_song_id');
        $this->addSql('ALTER TABLE song_suggestion DROP link');
    }
}
