<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nummer column to song table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE song ADD COLUMN nummer VARCHAR(20) DEFAULT NULL AFTER composer");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP COLUMN nummer');
    }
}
