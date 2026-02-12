<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add page column to post table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post ADD page VARCHAR(100) NOT NULL DEFAULT "beitraege"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post DROP page');
    }
}
