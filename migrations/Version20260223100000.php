<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add video_links JSON column to konzert table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE konzert ADD COLUMN video_links JSON NOT NULL DEFAULT ('{}') COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE konzert DROP COLUMN video_links');
    }
}
