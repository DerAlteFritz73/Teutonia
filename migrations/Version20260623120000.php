<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add etikett_in_pdf flag to song (skip Etikett stamping when already present)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD etikett_in_pdf TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP etikett_in_pdf');
    }
}
