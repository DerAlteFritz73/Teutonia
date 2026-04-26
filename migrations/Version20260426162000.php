<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add programm_vorderseite and programm_rueckseite to konzert table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert ADD programm_vorderseite VARCHAR(255) DEFAULT NULL, ADD programm_rueckseite VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert DROP programm_vorderseite, DROP programm_rueckseite');
    }
}
