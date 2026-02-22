<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add konzert table with song join, remove letzte_auffuehrung from song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE konzert (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            location VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE konzert_song (
            konzert_id INT NOT NULL,
            song_id INT NOT NULL,
            PRIMARY KEY(konzert_id, song_id),
            INDEX IDX_KONZERT_SONG_K (konzert_id),
            INDEX IDX_KONZERT_SONG_S (song_id),
            CONSTRAINT FK_KONZERT_SONG_K FOREIGN KEY (konzert_id) REFERENCES konzert (id) ON DELETE CASCADE,
            CONSTRAINT FK_KONZERT_SONG_S FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE song DROP COLUMN letzte_auffuehrung");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE song ADD letzte_auffuehrung DATE DEFAULT NULL");
        $this->addSql("DROP TABLE konzert_song");
        $this->addSql("DROP TABLE konzert");
    }
}
