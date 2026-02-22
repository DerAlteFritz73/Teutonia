<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gradient colours, image and image_position to style; add show_on_repertoire to song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE style ADD color VARCHAR(30) DEFAULT NULL, ADD color2 VARCHAR(30) DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL, ADD image_position VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE song ADD show_on_repertoire TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE style DROP COLUMN color, DROP COLUMN color2, DROP COLUMN image, DROP COLUMN image_position');
        $this->addSql('ALTER TABLE song DROP COLUMN show_on_repertoire');
    }
}
