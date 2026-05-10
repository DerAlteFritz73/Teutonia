<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add etikett and show_etikett columns to liederliste';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE liederliste ADD etikett VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE liederliste ADD show_etikett TINYINT(1) NOT NULL DEFAULT 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE liederliste DROP COLUMN etikett");
        $this->addSql("ALTER TABLE liederliste DROP COLUMN show_etikett");
    }
}
