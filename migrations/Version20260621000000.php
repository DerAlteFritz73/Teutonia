<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add arrangeur column to song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD arrangeur VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP arrangeur');
    }
}
