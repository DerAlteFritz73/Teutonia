<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dropboxlink column to song table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE song ADD COLUMN dropboxlink VARCHAR(500) DEFAULT NULL AFTER letzte_auffuehrung");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP COLUMN dropboxlink');
    }
}
