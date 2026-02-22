<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename song_keyword to song and song_keyword_style to song_style';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE song_keyword TO song, song_keyword_style TO song_style');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE song TO song_keyword, song_style TO song_keyword_style');
    }
}
