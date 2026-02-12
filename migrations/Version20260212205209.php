<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212205209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post CHANGE page page VARCHAR(100) NOT NULL, CHANGE font_title font_title VARCHAR(50) DEFAULT NULL, CHANGE font_subtitle font_subtitle VARCHAR(50) DEFAULT NULL, CHANGE font_paragraph font_paragraph VARCHAR(50) DEFAULT NULL, CHANGE font_title_bold font_title_bold TINYINT DEFAULT NULL, CHANGE font_title_italic font_title_italic TINYINT DEFAULT NULL, CHANGE font_title_underline font_title_underline TINYINT DEFAULT NULL, CHANGE font_subtitle_bold font_subtitle_bold TINYINT DEFAULT NULL, CHANGE font_subtitle_italic font_subtitle_italic TINYINT DEFAULT NULL, CHANGE font_subtitle_underline font_subtitle_underline TINYINT DEFAULT NULL, CHANGE font_paragraph_bold font_paragraph_bold TINYINT DEFAULT NULL, CHANGE font_paragraph_italic font_paragraph_italic TINYINT DEFAULT NULL, CHANGE font_paragraph_underline font_paragraph_underline TINYINT DEFAULT NULL, CHANGE position position INT DEFAULT NULL, CHANGE image_rotation image_rotation INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post CHANGE page page VARCHAR(100) DEFAULT \'beitraege\' NOT NULL, CHANGE font_title font_title VARCHAR(50) DEFAULT \'default\', CHANGE font_title_bold font_title_bold TINYINT DEFAULT 0, CHANGE font_title_italic font_title_italic TINYINT DEFAULT 0, CHANGE font_title_underline font_title_underline TINYINT DEFAULT 0, CHANGE font_subtitle font_subtitle VARCHAR(50) DEFAULT \'default\', CHANGE font_subtitle_bold font_subtitle_bold TINYINT DEFAULT 0, CHANGE font_subtitle_italic font_subtitle_italic TINYINT DEFAULT 0, CHANGE font_subtitle_underline font_subtitle_underline TINYINT DEFAULT 0, CHANGE font_paragraph font_paragraph VARCHAR(50) DEFAULT \'default\', CHANGE font_paragraph_bold font_paragraph_bold TINYINT DEFAULT 0, CHANGE font_paragraph_italic font_paragraph_italic TINYINT DEFAULT 0, CHANGE font_paragraph_underline font_paragraph_underline TINYINT DEFAULT 0, CHANGE position position INT DEFAULT 0, CHANGE image_rotation image_rotation INT DEFAULT 0');
    }
}
