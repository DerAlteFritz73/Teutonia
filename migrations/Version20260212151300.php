<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212151300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position field to post table for drag and drop ordering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post ADD COLUMN position INT DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post DROP COLUMN position');
    }
}
