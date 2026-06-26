<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add score_sync_anchors table for the Mitlesen / moving-cursor feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE score_sync_anchors (
            id INT AUTO_INCREMENT NOT NULL,
            song_id INT NOT NULL,
            pdf_path VARCHAR(500) NOT NULL,
            audio_path VARCHAR(500) NOT NULL,
            anchors JSON NOT NULL,
            UNIQUE INDEX uniq_sync (song_id, pdf_path(200), audio_path(200)),
            PRIMARY KEY (id),
            CONSTRAINT fk_sync_song FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE score_sync_anchors');
    }
}
