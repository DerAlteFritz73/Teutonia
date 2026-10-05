<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the song folder path columns now that files come from the local archive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song CHANGE dropboxlink archive_path VARCHAR(500) DEFAULT NULL, CHANGE aktuelle_dropboxlink aktuelle_archive_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song CHANGE archive_path dropboxlink VARCHAR(500) DEFAULT NULL, CHANGE aktuelle_archive_path aktuelle_dropboxlink VARCHAR(500) DEFAULT NULL');
    }
}
