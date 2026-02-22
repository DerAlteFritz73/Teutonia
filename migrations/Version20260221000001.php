<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add style_repertoire_songs join table and drop show_on_repertoire from song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE style_repertoire_songs (
            style_id INT NOT NULL,
            song_id INT NOT NULL,
            PRIMARY KEY (style_id, song_id),
            INDEX IDX_STYLE_REP_STYLE (style_id),
            INDEX IDX_STYLE_REP_SONG (song_id),
            CONSTRAINT FK_STYLE_REP_STYLE FOREIGN KEY (style_id) REFERENCES style (id) ON DELETE CASCADE,
            CONSTRAINT FK_STYLE_REP_SONG FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE song DROP COLUMN show_on_repertoire');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE style_repertoire_songs');
        $this->addSql('ALTER TABLE song ADD show_on_repertoire TINYINT(1) NOT NULL DEFAULT 0');
    }
}
