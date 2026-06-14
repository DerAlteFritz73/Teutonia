<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blacklisted_ip table for IP blocking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blacklisted_ip (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, reason LONGTEXT, blacklisted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_D5A0A18E8A7D37C0 (ip_address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blacklisted_ip');
    }
}
