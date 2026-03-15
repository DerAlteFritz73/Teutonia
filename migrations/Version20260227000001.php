<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace hardcoded singer count in post title with {singer_count} placeholder';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE post SET title = REPLACE(title, '32 Sängerinnen', '{singer_count} Sängerinnen') WHERE title LIKE '%32 Sängerinnen%'");
    }

    public function down(Schema $schema): void
    {
        // Cannot reverse without knowing the current user count; left as no-op
    }
}
