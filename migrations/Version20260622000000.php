<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split etikett into etikett_color and etikett_number';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song ADD etikett_color VARCHAR(20) DEFAULT NULL, ADD etikett_number VARCHAR(30) DEFAULT NULL, CHANGE etikett etikett VARCHAR(30) DEFAULT NULL');
        // Backfill: colour = first word, number = the rest (character-based, utf8mb4-safe).
        $this->addSql(<<<'SQL'
            UPDATE song
            SET etikett_color = NULLIF(TRIM(SUBSTRING_INDEX(etikett, ' ', 1)), ''),
                etikett_number = NULLIF(TRIM(CASE WHEN LOCATE(' ', etikett) > 0 THEN SUBSTRING(etikett, LOCATE(' ', etikett) + 1) ELSE '' END), '')
            WHERE etikett IS NOT NULL AND etikett <> ''
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE song DROP etikett_color, DROP etikett_number, CHANGE etikett etikett VARCHAR(20) DEFAULT NULL');
    }
}
