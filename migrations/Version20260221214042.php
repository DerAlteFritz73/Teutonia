<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221214042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to style_id FKs so styles can be deleted';
    }

    public function up(Schema $schema): void
    {
        // Fix song_style: style_id FK must cascade when style is deleted
        $this->addSql('ALTER TABLE song_style DROP FOREIGN KEY song_style_ibfk_2');
        $this->addSql('ALTER TABLE song_style ADD CONSTRAINT song_style_ibfk_2 FOREIGN KEY (style_id) REFERENCES style (id) ON DELETE CASCADE');

        // Fix style_repertoire_songs: style_id FK must cascade when style is deleted
        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY FK_435B2BEEBACD6074');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT FK_435B2BEEBACD6074 FOREIGN KEY (style_id) REFERENCES style (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song_style DROP FOREIGN KEY song_style_ibfk_2');
        $this->addSql('ALTER TABLE song_style ADD CONSTRAINT song_style_ibfk_2 FOREIGN KEY (style_id) REFERENCES style (id)');

        $this->addSql('ALTER TABLE style_repertoire_songs DROP FOREIGN KEY FK_435B2BEEBACD6074');
        $this->addSql('ALTER TABLE style_repertoire_songs ADD CONSTRAINT FK_435B2BEEBACD6074 FOREIGN KEY (style_id) REFERENCES style (id)');
    }
}
