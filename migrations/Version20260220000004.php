<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop old etikett column and rename nummer to etikett in song table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP COLUMN etikett, CHANGE nummer etikett VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song CHANGE etikett nummer VARCHAR(20) DEFAULT NULL, ADD etikett VARCHAR(50) DEFAULT NULL');
    }
}
