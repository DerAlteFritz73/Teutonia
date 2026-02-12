<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201102148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Drop email unique index
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');

        // Add username column as nullable first
        $this->addSql('ALTER TABLE user ADD username VARCHAR(50) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL');

        // Set username for existing users based on email
        $this->addSql("UPDATE user SET username = 'admin' WHERE username IS NULL");

        // Make username NOT NULL and add unique index
        $this->addSql('ALTER TABLE user CHANGE username username VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8D93D649F85E0677 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP username, CHANGE email email VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON `user` (email)');
    }
}
