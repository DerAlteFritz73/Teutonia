<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221140000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE konzert ADD custom_songs JSON NOT NULL DEFAULT ('[]')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert DROP COLUMN custom_songs');
    }
}
