<?php

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;
use Facebook\WebDriver\WebDriverBy;

/**
 * Full-coverage test: visits every page, clicks every song accordion,
 * verifies file-loading spinners disappear, and exercises key interactions.
 *
 * Run via the admin test runner or: APP_ENV=test ./vendor/bin/phpunit tests/E2E/FullCoverageTest.php
 */
class FullCoverageTest extends AbstractE2ETestCase
{
    // ── Public pages ──────────────────────────────────────────────────────

    public function testHomepageLoads(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');
        $this->assertSelectorExists('body');
        $this->assertSelectorExists('nav');
    }

    /**
     * @dataProvider publicPageProvider
     */
    public function testPublicPageLoads(string $url, string $expectedText): void
    {
        $client = static::createPantherClient();
        $client->request('GET', $url);
        $this->assertSelectorTextContains('body', $expectedText);
    }

    public static function publicPageProvider(): array
    {
        return [
            'unser-chor'     => ['/unser-chor',                    'Unser Chor'],
            'konzerte'       => ['/konzerte-und-aktivitaeten',      'Konzerte'],
            'historie'       => ['/historie',                       'Historie'],
            'chorproben'     => ['/chorproben',                     'Chorproben'],
            'repertoire'     => ['/unser-repertoire',               'Repertoire'],
            'termine'        => ['/unsere-naechsten-termine',       'Termine'],
            'geselliges'     => ['/geselliges',                     'Geselliges'],
            'beitraege'      => ['/beitraege',                      'Beiträge'],
            'login'          => ['/login',                          'Anmelden'],
        ];
    }

    public function testPublicRepertoireHasWordCloud(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/unser-repertoire');
        $this->assertSelectorExists('#wordcloud');
        $this->assertSelectorExists('#wordcloud-container');
    }

    public function testLoginRedirectsToMemberAreaWhenAlreadyLoggedIn(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/login');
        $this->assertStringNotContainsString('/login', $client->getCurrentURL());
    }

    public function testUnauthenticatedAccessToMemberAreaRedirectsToLogin(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/mitglieder');
        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    // ── Member pages ──────────────────────────────────────────────────────

    public function testMemberDashboardLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder');
        $this->assertSelectorTextContains('h1', 'Willkommen');
    }

    public function testMemberArchivLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/archiv');
        $this->assertSelectorExists('body');
    }

    public function testMemberKonzerteLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/konzerte');
        $this->assertSelectorTextContains('body', 'Sommerkonzert 2024');
    }

    public function testMemberGoogleKalenderLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/google-kalender');
        $this->assertSelectorTextContains('h1', 'Kalender');
    }

    public function testMemberIdeenkisteLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/ideenkiste');
        $this->assertSelectorTextContains('body', 'Hallelujah');
    }

    // ── Aktuelle Proben: every song accordion ─────────────────────────────

    public function testAktuelleProbenPageStructure(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('h1');

        $this->assertSelectorTextContains('h1', 'Aktuelle Proben');
        $this->assertSelectorExists('#probenAccordion');
        $this->assertSelectorExists('#pdfViewerModal');
        $this->assertSelectorExists('#audioPlayerModal');
    }

    public function testAktuelleProbenHasExpectedFixtureSongs(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');

        $source = $client->getWebDriver()->getPageSource();
        $this->assertStringContainsString('Dona Nobis Pacem', $source);
        $this->assertStringContainsString('Halleluja Chorus', $source);
    }

    public function testEveryAktuelleProbenAccordionOpensAndSpinnerDisappears(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#probenAccordion');

        $buttons = $client->findElements(
            WebDriverBy::cssSelector('#probenAccordion > .accordion-item > .accordion-header .accordion-btn-files')
        );

        $this->assertNotEmpty($buttons, 'No Aktuelle Proben songs found — check fixtures');

        foreach ($buttons as $btn) {
            $songName = trim($btn->getText());
            $panelId  = ltrim($btn->getAttribute('data-bs-target') ?? '', '#');

            $btn->click();

            // Wait for Bootstrap to open the panel
            $client->waitFor('#' . $panelId . '.show');

            // Wait for any file-loading spinner to be replaced (files, "Keine Dateien", or error).
            // Songs with no Dropbox path have no spinner at all, so this returns immediately.
            $this->waitForSpinnerGone($client->getWebDriver(), '#' . $panelId);

            // Panel must be open — content may be empty for songs with no files/links/movements
            $this->assertSelectorExists('#' . $panelId . '.show',
                "Accordion panel did not open for song: $songName");
        }
    }

    public function testSongWithoutDropboxPathAccordionOpens(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#probenAccordion');

        // "Dona Nobis Pacem" has no Dropbox path — the accordion should open cleanly
        $btn = $client->findElement(
            WebDriverBy::xpath('//*[@id="probenAccordion"]//button[contains(., "Dona Nobis Pacem")]')
        );
        $panelId = ltrim($btn->getAttribute('data-bs-target') ?? '', '#');
        $btn->click();
        $client->waitFor('#' . $panelId . '.show');

        // Panel is open — no spinner (nothing to load), no JS errors
        $this->assertSelectorExists('#' . $panelId . '.show');
    }

    // ── Noten section: every song accordion ──────────────────────────────

    public function testNotenSectionPresentOnProbenPage(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#allFilesAccordion');

        $this->assertSelectorExists('#allFilesAccordion');
        $this->assertSelectorExists('#notenSearch');
    }

    public function testEveryNotenSongAccordionOpensAndSpinnerDisappears(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#allFilesAccordion');

        $buttons = $client->findElements(
            WebDriverBy::cssSelector('#allFilesAccordion > .accordion-item > .accordion-header .accordion-btn-files')
        );

        $this->assertNotEmpty($buttons, 'No Noten songs found — check fixtures');

        foreach ($buttons as $btn) {
            $songName = trim($btn->getText());
            $panelId  = ltrim($btn->getAttribute('data-bs-target') ?? '', '#');

            $btn->click();
            $client->waitFor('#' . $panelId . '.show');
            $this->waitForSpinnerGone($client->getWebDriver(), '#' . $panelId);

            // Verify panel is open and not blank
            $panel = $client->findElement(WebDriverBy::id($panelId));
            $this->assertTrue($panel->isDisplayed(), "Panel not visible for: $songName");

            // If the song has movements (Sätze), click each movement accordion too
            $this->clickMovementAccordions($client, $panelId);
        }
    }

    public function testSongWithMovementsShowsChildAccordions(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#allFilesAccordion');

        // "Requiem" has 2 child movements in the fixtures
        $btn = $client->findElement(
            WebDriverBy::xpath('//*[@id="allFilesAccordion"]//button[contains(., "Requiem")]')
        );
        $panelId = ltrim($btn->getAttribute('data-bs-target') ?? '', '#');
        $btn->click();
        $client->waitFor('#' . $panelId . '.show');
        $this->waitForSpinnerGone($client->getWebDriver(), '#' . $panelId);

        // Should show a sub-accordion with movement buttons
        $movementButtons = $client->findElements(
            WebDriverBy::cssSelector('#' . $panelId . ' .accordion-btn-files')
        );
        $this->assertCount(2, $movementButtons, 'Requiem should have 2 movement accordions');

        // Click each movement
        foreach ($movementButtons as $movBtn) {
            $movPanelId = ltrim($movBtn->getAttribute('data-bs-target') ?? '', '#');
            $movBtn->click();
            $client->waitFor('#' . $movPanelId . '.show');
            $this->waitForSpinnerGone($client->getWebDriver(), '#' . $movPanelId);
        }
    }

    // ── Noten search ─────────────────────────────────────────────────────

    public function testNotenSearchFiltersSongs(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#notenSearch');

        // Count visible accordion items before search
        $allItems = $client->findElements(
            WebDriverBy::cssSelector('#allFilesAccordion .accordion-item')
        );
        $totalCount = count($allItems);

        // Type a search term that matches only one song
        $searchInput = $client->findElement(WebDriverBy::id('notenSearch'));
        $searchInput->sendKeys('Amazing');

        // Wait briefly for the JS filter to run
        usleep(300_000);

        // Count visible items after search
        $driver = $client->getWebDriver();
        $visibleCount = $driver->executeScript(
            "return Array.from(document.querySelectorAll('#allFilesAccordion .accordion-item'))
                .filter(el => el.style.display !== 'none').length;"
        );

        $this->assertLessThan($totalCount, (int) $visibleCount, 'Search should hide non-matching songs');
        $this->assertGreaterThan(0, (int) $visibleCount, 'Search should keep at least one matching song');
    }

    public function testNotenSearchShowsNoResultsMessage(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');
        $client->waitFor('#notenSearch');

        $searchInput = $client->findElement(WebDriverBy::id('notenSearch'));
        $searchInput->sendKeys('xyznonexistentsong12345');

        usleep(300_000);

        $noResultsDiv = $client->findElement(WebDriverBy::id('notenNoResults'));
        $this->assertTrue($noResultsDiv->isDisplayed(), 'No-results message should be visible');
    }

    // ── Ideenkiste ────────────────────────────────────────────────────────

    public function testIdeenkisteNewFormShowsAndSubmits(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/ideenkiste/neu');
        $this->assertSelectorExists('form');

        $client->submitForm('Vorschlag einreichen', [
            'song_suggestion[title]'       => 'Stille Nacht',
            'song_suggestion[artist]'      => 'Franz Gruber',
            'song_suggestion[description]' => 'Weihnachtslied',
        ]);

        $client->request('GET', '/mitglieder/ideenkiste');
        $this->assertSelectorTextContains('body', 'Stille Nacht');
    }

    public function testIdeenkisteSortByPopular(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/ideenkiste?sort=popular');
        $this->assertSelectorTextContains('body', 'Hallelujah');
    }

    // ── Admin pages ───────────────────────────────────────────────────────

    /**
     * @dataProvider adminPageProvider
     */
    public function testAdminPageLoads(string $url, string $expectedText): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', $url);
        $this->assertSelectorTextContains('body', $expectedText);
    }

    public static function adminPageProvider(): array
    {
        return [
            'dashboard' => ['/admin',             'Administration'],
            'songs'     => ['/admin/songs',        'Lieder'],
            'konzerte'  => ['/admin/konzerte',     'Konzerte'],
            'users'     => ['/admin/users',        'Benutzer'],
            'beitraege' => ['/admin/beitraege',    'Beiträge'],
            'styles'    => ['/admin/styles',       'Stile'],
            'logs'      => ['/admin/logs',         'Logs'],
            'tests'     => ['/admin/tests',        'Tests'],
        ];
    }

    public function testNonAdminCannotAccessAdminArea(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin');
        // Symfony returns 403 at the same URL — verify admin dashboard content is NOT shown
        $source = $client->getWebDriver()->getPageSource();
        $this->assertStringNotContainsString('btn-hetzner-terminal', $source,
            'Regular member should not see the admin dashboard');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Wait up to 5 s for all .file-loading-spinner elements inside $panelSelector
     * to be replaced (removed from DOM) by file lists or status messages.
     */
    private function waitForSpinnerGone(\Facebook\WebDriver\Remote\RemoteWebDriver $driver, string $panelSelector): void
    {
        // Use direct-child combinators so we only watch the spinner in THIS panel's
        // own song-files-container, not spinners inside nested movement sub-panels.
        // 15 s covers slow Dropbox token refresh / network failures on the Pi.
        $selector = $panelSelector . ' > .accordion-body > .song-files-container .file-loading-spinner';
        $driver->wait(15, 300)->until(function ($d) use ($selector) {
            return empty($d->findElements(WebDriverBy::cssSelector($selector)));
        });
    }

    /**
     * Find and click every movement (Sätze) accordion inside an open parent panel,
     * waiting for each to open and for its spinner to disappear.
     */
    private function clickMovementAccordions(\Symfony\Component\Panther\Client $client, string $parentPanelId): void
    {
        $movementBtns = $client->findElements(
            WebDriverBy::cssSelector('#' . $parentPanelId . ' .accordion-btn-files')
        );

        foreach ($movementBtns as $movBtn) {
            $movPanelId = ltrim($movBtn->getAttribute('data-bs-target') ?? '', '#');
            if (empty($movPanelId)) {
                continue;
            }

            $movBtn->click();
            $client->waitFor('#' . $movPanelId . '.show');
            $this->waitForSpinnerGone($client->getWebDriver(), '#' . $movPanelId);
        }
    }
}
