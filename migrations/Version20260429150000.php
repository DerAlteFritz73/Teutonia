<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add title column to liederliste';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE liederliste ADD title VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE liederliste DROP COLUMN title");
    }
}
