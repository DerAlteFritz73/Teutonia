<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add show_etikett column to liederliste';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE liederliste ADD show_etikett TINYINT(1) NOT NULL DEFAULT 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE liederliste DROP COLUMN show_etikett");
    }
}
