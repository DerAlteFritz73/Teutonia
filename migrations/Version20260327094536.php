<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327094536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isAktuelleProben flag and aktuelleDropboxlink; migrate existing Aktuelle Proben songs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD is_aktuelle_proben TINYINT DEFAULT 0 NOT NULL, ADD aktuelle_dropboxlink VARCHAR(500) DEFAULT NULL');
        $this->addSql("UPDATE song SET is_aktuelle_proben = 1, aktuelle_dropboxlink = dropboxlink WHERE folder = 'Aktuelle Proben'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song DROP is_aktuelle_proben, DROP aktuelle_dropboxlink');
    }
}
