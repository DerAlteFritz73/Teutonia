<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221214335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_style ADD CONSTRAINT FK_FE192D2FABF5DF42 FOREIGN KEY (song_keyword_id) REFERENCES song (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_FE192D2FABF5DF42 ON song_style (song_keyword_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_style DROP FOREIGN KEY FK_FE192D2FABF5DF42');
        $this->addSql('DROP INDEX IDX_FE192D2FABF5DF42 ON song_style');
    }
}
