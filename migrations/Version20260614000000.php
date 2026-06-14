<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add introduction column to konzert';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE konzert ADD introduction LONGTEXT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE konzert DROP COLUMN introduction");
    }
}
