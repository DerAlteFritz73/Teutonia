<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221150000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD parent_id INT NULL');
        $this->addSql('ALTER TABLE song ADD CONSTRAINT FK_song_parent FOREIGN KEY (parent_id) REFERENCES song(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_song_parent ON song(parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP FOREIGN KEY FK_song_parent');
        $this->addSql('DROP INDEX IDX_song_parent ON song');
        $this->addSql('ALTER TABLE song DROP COLUMN parent_id');
    }
}
