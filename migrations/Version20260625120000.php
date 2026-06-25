<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop etikett_in_pdf flag from song (feature removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP etikett_in_pdf');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD etikett_in_pdf TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
