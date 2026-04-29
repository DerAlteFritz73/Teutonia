<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add kritik_path to konzert table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert ADD kritik_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert DROP kritik_path');
    }
}
