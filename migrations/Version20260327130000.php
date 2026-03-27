<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore style image column to VARCHAR(255) for file uploads (was temporarily VARCHAR(10) for emoji)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE style MODIFY image VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE style SET image = NULL WHERE image IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE style MODIFY image VARCHAR(10) DEFAULT NULL');
    }
}
