<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Collapse double <br> tags in post paragraphs that were introduced by a bug
 * in the Quill-to-br conversion function (each Enter produced 2 <br> instead of 1).
 */
final class Version20260222120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix double <br> in post paragraphs caused by buggy Quill serialisation';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, paragraph FROM post WHERE paragraph IS NOT NULL AND paragraph != ''"
        );

        foreach ($rows as $row) {
            // Replace every pair of consecutive <br> tags (any variant) with a single <br>.
            // One pass halves the count, which exactly undoes the doubling the old JS did.
            $fixed = preg_replace('/(<br\s*\/?>)\s*(<br\s*\/?>)/i', '<br>', $row['paragraph']);

            if ($fixed !== $row['paragraph']) {
                $this->connection->executeStatement(
                    'UPDATE post SET paragraph = ? WHERE id = ?',
                    [$fixed, $row['id']]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Not reversible — we cannot distinguish originally-doubled from intentional double breaks.
    }
}
