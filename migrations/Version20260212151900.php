<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212151900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add imageRotation field to post table for image rotation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post ADD COLUMN image_rotation INT DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post DROP COLUMN image_rotation');
    }
}
