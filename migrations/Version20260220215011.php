<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220215011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY `FK_STYLE_REP_SONG`');
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY `FK_STYLE_REP_STYLE`');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT FK_435B2BEEBACD6074 FOREIGN KEY (style_id) REFERENCES style (id)');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT FK_435B2BEEA0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id)');
        $this->addSql('ALTER TABLE style_repertoire_songs RENAME INDEX idx_style_rep_style TO IDX_435B2BEEBACD6074');
        $this->addSql('ALTER TABLE style_repertoire_songs RENAME INDEX idx_style_rep_song TO IDX_435B2BEEA0BDB2F3');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY FK_435B2BEEBACD6074');
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY FK_435B2BEEA0BDB2F3');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT `FK_STYLE_REP_SONG` FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT `FK_STYLE_REP_STYLE` FOREIGN KEY (style_id) REFERENCES style (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE style_repertoire_songs RENAME INDEX idx_435b2beebacd6074 TO IDX_STYLE_REP_STYLE');
        $this->addSql('ALTER TABLE style_repertoire_songs RENAME INDEX idx_435b2beea0bdb2f3 TO IDX_STYLE_REP_SONG');
    }
}
