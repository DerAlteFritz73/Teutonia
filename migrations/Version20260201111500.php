<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add song and voicePart fields to practice_link table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice_link ADD song VARCHAR(255) DEFAULT NULL, ADD voice_part VARCHAR(20) DEFAULT NULL');
        $this->addSql("UPDATE practice_link SET song = title WHERE song IS NULL");
        $this->addSql('ALTER TABLE practice_link CHANGE song song VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice_link DROP song, DROP voice_part');
    }
}
