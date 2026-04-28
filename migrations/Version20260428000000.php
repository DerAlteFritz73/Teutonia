<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ausfuehrende key from custom_songs JSON in konzert table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE konzert
            SET custom_songs = (
                SELECT JSON_ARRAYAGG(
                    JSON_REMOVE(elem, '$."ausfuehrende"')
                )
                FROM JSON_TABLE(custom_songs, '$[*]' COLUMNS (
                    elem JSON PATH '$'
                )) AS jt
            )
            WHERE custom_songs IS NOT NULL
              AND JSON_LENGTH(custom_songs) > 0
        SQL);
    }

    public function down(Schema $schema): void
    {
        // ausfuehrende data cannot be restored once removed
    }
}
