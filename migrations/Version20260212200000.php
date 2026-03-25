<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add font columns to post table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE post ADD font_title VARCHAR(50) DEFAULT 'default'");
        $this->addSql("ALTER TABLE post ADD font_subtitle VARCHAR(50) DEFAULT 'default'");
        $this->addSql("ALTER TABLE post ADD font_paragraph VARCHAR(50) DEFAULT 'default'");
        $this->addSql('ALTER TABLE post ADD font_title_bold TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_title_italic TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_title_underline TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_subtitle_bold TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_subtitle_italic TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_subtitle_underline TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_paragraph_bold TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_paragraph_italic TINYINT DEFAULT 0');
        $this->addSql('ALTER TABLE post ADD font_paragraph_underline TINYINT DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post DROP font_title, DROP font_subtitle, DROP font_paragraph');
        $this->addSql('ALTER TABLE post DROP font_title_bold, DROP font_title_italic, DROP font_title_underline');
        $this->addSql('ALTER TABLE post DROP font_subtitle_bold, DROP font_subtitle_italic, DROP font_subtitle_underline');
        $this->addSql('ALTER TABLE post DROP font_paragraph_bold, DROP font_paragraph_italic, DROP font_paragraph_underline');
    }
}
