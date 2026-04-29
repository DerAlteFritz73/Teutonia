<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add song_notes column to konzert table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE konzert ADD song_notes JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert DROP COLUMN song_notes');
    }
}
