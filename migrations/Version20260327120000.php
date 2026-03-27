<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace style image file with emoji: drop image_position, shrink image column to 10 chars';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE style DROP COLUMN image_position, MODIFY image VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE style ADD image_position VARCHAR(20) DEFAULT NULL, MODIFY image VARCHAR(255) DEFAULT NULL');
    }
}
