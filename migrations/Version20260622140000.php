<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Follow-up data fixes for songs:
 *  - composition_year 1599 for "Wie schön leuchtet der Morgenstern" (Nicolai chorale).
 *  - Correct composer where the DB stored a performer/lyricist instead of the composer.
 *
 * Composer updates are guarded on the known-wrong value, so they only change rows
 * still holding the bad data and are safe to re-run.
 */
final class Version20260622140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add 1599 for Morgenstern; correct 4 mis-attributed composers';
    }

    public function up(Schema $schema): void
    {
        // Wie schön leuchtet der Morgenstern (Philipp Nicolai chorale, 1599)
        $this->addSql(<<<'SQL'
            UPDATE song SET composition_year = '1599'
            WHERE id IN (2061, 2062) AND (composition_year IS NULL OR composition_year = '')
            SQL);

        // Composer corrections (DB had a performer or lyricist, not the composer)
        $this->addSql("UPDATE song SET composer = 'Werner Richard Heymann' WHERE id = 1834 AND composer = 'Hans-Dieter Kuhn'"); // Das gibt's nur einmal
        $this->addSql("UPDATE song SET composer = 'Peter Kreuder' WHERE id = 1989 AND composer = 'Peter Alexander'");           // Sag beim Abschied leise Servus
        $this->addSql("UPDATE song SET composer = 'Walter Jurmann' WHERE id = 2039 AND composer = 'Fritz Rotter'");             // Veronika, der Lenz ist da (music)
        $this->addSql("UPDATE song SET composer = 'Heinz Gietz' WHERE id = 2138 AND composer = 'Bill Ramsey'");                 // Ohne Krimi geht die Mimi nie ins Bett
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE song SET composer = 'Hans-Dieter Kuhn' WHERE id = 1834 AND composer = 'Werner Richard Heymann'");
        $this->addSql("UPDATE song SET composer = 'Peter Alexander' WHERE id = 1989 AND composer = 'Peter Kreuder'");
        $this->addSql("UPDATE song SET composer = 'Fritz Rotter' WHERE id = 2039 AND composer = 'Walter Jurmann'");
        $this->addSql("UPDATE song SET composer = 'Bill Ramsey' WHERE id = 2138 AND composer = 'Heinz Gietz'");
        $this->addSql("UPDATE song SET composition_year = NULL WHERE id IN (2061, 2062)");
    }
}
