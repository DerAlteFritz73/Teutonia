<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill composition_year for well-documented works (web-researched, verified).
 *
 * Only fills rows where composition_year is still empty, so it never overwrites a
 * year entered by hand and is safe to re-run. Keyed by song id (ids verified
 * identical on dev and prod at the time of writing).
 */
final class Version20260622130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill composition_year for 64 well-documented songs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE song
            SET composition_year = CASE id
                WHEN 50 THEN '1939'   WHEN 53 THEN '1827'   WHEN 61 THEN '1747'
                WHEN 63 THEN '1982'   WHEN 67 THEN '1895'   WHEN 73 THEN '1939'
                WHEN 76 THEN '1858'   WHEN 77 THEN '1849'   WHEN 86 THEN '1539'
                WHEN 88 THEN '1967'   WHEN 93 THEN '1964'   WHEN 1806 THEN '1834'
                WHEN 1818 THEN '1969' WHEN 1821 THEN '1887' WHEN 1822 THEN '1791'
                WHEN 1827 THEN '1961' WHEN 1834 THEN '1931' WHEN 1835 THEN '1946'
                WHEN 1838 THEN '1971' WHEN 1844 THEN '1837' WHEN 1845 THEN '1843'
                WHEN 1856 THEN '1930' WHEN 1866 THEN '1609' WHEN 1875 THEN '1826'
                WHEN 1877 THEN '1824' WHEN 1884 THEN '1842' WHEN 1898 THEN '1984'
                WHEN 1900 THEN '1846' WHEN 1920 THEN '1822' WHEN 1922 THEN '1825'
                WHEN 1937 THEN '1860' WHEN 1942 THEN '1959' WHEN 1944 THEN '1851'
                WHEN 1945 THEN '1945' WHEN 1951 THEN '1895' WHEN 1952 THEN '1821'
                WHEN 1958 THEN '1984' WHEN 1961 THEN '1869' WHEN 1968 THEN '1965'
                WHEN 1969 THEN '1934' WHEN 1973 THEN '1977' WHEN 1982 THEN '1898'
                WHEN 1985 THEN '1872' WHEN 1989 THEN '1936' WHEN 1998 THEN '1965'
                WHEN 2002 THEN '1808' WHEN 2030 THEN '1853' WHEN 2036 THEN '1787'
                WHEN 2038 THEN '1831' WHEN 2039 THEN '1930' WHEN 2064 THEN '1725'
                WHEN 2072 THEN '1965' WHEN 2079 THEN '1930' WHEN 2080 THEN '1962'
                WHEN 2107 THEN '1974' WHEN 2110 THEN '1971' WHEN 2112 THEN '1956'
                WHEN 2138 THEN '1962' WHEN 2189 THEN '1917' WHEN 2225 THEN '1930'
                WHEN 2228 THEN '1905' WHEN 2234 THEN '1957' WHEN 2289 THEN 'um 1715'
                WHEN 2306 THEN '1984'
            END
            WHERE id IN (
                50,53,61,63,67,73,76,77,86,88,93,1806,1818,1821,1822,1827,1834,1835,
                1838,1844,1845,1856,1866,1875,1877,1884,1898,1900,1920,1922,1937,1942,
                1944,1945,1951,1952,1958,1961,1968,1969,1973,1982,1985,1989,1998,2002,
                2030,2036,2038,2039,2064,2072,2079,2080,2107,2110,2112,2138,2189,2225,
                2228,2234,2289,2306
            )
            AND (composition_year IS NULL OR composition_year = '')
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Data-only migration: clear the years it set (best-effort revert).
        $this->addSql(<<<'SQL'
            UPDATE song SET composition_year = NULL
            WHERE id IN (
                50,53,61,63,67,73,76,77,86,88,93,1806,1818,1821,1822,1827,1834,1835,
                1838,1844,1845,1856,1866,1875,1877,1884,1898,1900,1920,1922,1937,1942,
                1944,1945,1951,1952,1958,1961,1968,1969,1973,1982,1985,1989,1998,2002,
                2030,2036,2038,2039,2064,2072,2079,2080,2107,2110,2112,2138,2189,2225,
                2228,2234,2289,2306
            )
            SQL);
    }
}
