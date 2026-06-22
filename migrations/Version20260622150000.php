<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cached duration column to song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD duration VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP duration');
    }
}
