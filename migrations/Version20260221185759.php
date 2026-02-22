<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221185759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE konzert_song RENAME INDEX idx_konzert_song_k TO IDX_903F58B26258E2C7');
        $this->addSql('ALTER TABLE konzert_song RENAME INDEX idx_konzert_song_s TO IDX_903F58B2A0BDB2F3');
        $this->addSql('ALTER TABLE song RENAME INDEX idx_song_parent TO IDX_33EDEEA1727ACA70');
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY `FK_435B2BEEA0BDB2F3`');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT FK_435B2BEEA0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE konzert_song RENAME INDEX idx_903f58b26258e2c7 TO IDX_KONZERT_SONG_K');
        $this->addSql('ALTER TABLE konzert_song RENAME INDEX idx_903f58b2a0bdb2f3 TO IDX_KONZERT_SONG_S');
        $this->addSql('ALTER TABLE song RENAME INDEX idx_33edeea1727aca70 TO IDX_song_parent');
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY FK_435B2BEEA0BDB2F3');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT `FK_435B2BEEA0BDB2F3` FOREIGN KEY (song_id) REFERENCES song (id)');
    }
}
