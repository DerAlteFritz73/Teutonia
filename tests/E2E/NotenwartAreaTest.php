<?php

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;
use Facebook\WebDriver\WebDriverBy;

/**
 * Tests the NOTENWART role: a read-only Liederlisten manager.
 *
 * A Notenwart may
 *   - see a "LiederListe" link instead of the "Admin" menu,
 *   - browse the Liederlisten list and open a list to view it,
 * but may NOT
 *   - reach any other admin area,
 *   - create, edit, reorder, or delete anything.
 */
class NotenwartAreaTest extends AbstractE2ETestCase
{
    /** Logs in as the seeded Notenwart user. */
    private function loginAsNotenwart()
    {
        return $this->createLoggedInClient(AppFixtures::NOTENWART_USERNAME, AppFixtures::PASSWORD);
    }

    public function testNavShowsLiederListeLinkInsteadOfAdminMenu(): void
    {
        $client = $this->loginAsNotenwart();
        $client->request('GET', '/mitglieder');

        // The "LiederListe" link points at the Liederlisten page…
        $this->assertSelectorExists('a[href$="/admin/liederlisten"]');
        $this->assertSelectorTextContains('a[href$="/admin/liederlisten"]', 'LiederListe');

        // …and the admin gear menu (its only marker) is absent.
        $this->assertStringNotContainsString(
            'bi-gear-fill',
            $client->getWebDriver()->getPageSource(),
            'Notenwart users must not see the Admin menu.'
        );
    }

    public function testCanViewLiederlistenIndex(): void
    {
        $client = $this->loginAsNotenwart();
        $client->request('GET', '/admin/liederlisten');

        $this->assertSelectorTextContains('h1', 'Liederlisten');
        $this->assertSelectorTextContains('body', 'Notenwart Liste');
    }

    public function testIndexHidesCreateAndDeleteActions(): void
    {
        $client = $this->loginAsNotenwart();
        $client->request('GET', '/admin/liederlisten');

        $source = $client->getWebDriver()->getPageSource();

        // No "Neue Liste" create button and no delete form/route for read-only users.
        $this->assertStringNotContainsString('admin_liederlisten_new', $source);
        $this->assertStringNotContainsString('/delete', $source);
        $this->assertSelectorNotExists('.btn-outline-danger');

        // A read-only "view" (eye) action is offered instead.
        $this->assertSelectorExists('a[title="Ansehen"]');
    }

    public function testEditPageIsReadOnly(): void
    {
        $client = $this->loginAsNotenwart();
        $client->request('GET', '/admin/liederlisten');

        // Follow the read-only view link to the list's edit page.
        $viewLink = $client->getWebDriver()->findElement(
            WebDriverBy::cssSelector('a[title="Ansehen"]')
        );
        $editUrl = $viewLink->getAttribute('href');
        $client->request('GET', $editUrl);

        // The right list (songs in the list) is rendered…
        $client->waitFor('#right-list .ll-row');
        $source = $client->getWebDriver()->getPageSource();
        $this->assertStringContainsString('Notenwart Test Zugabe', $source);

        // …but every editing affordance is gone.
        $this->assertSelectorNotExists('#save-btn');
        $this->assertStringContainsString('Nur-Lese-Ansicht', $source);

        // The name field is read-only and no per-row delete buttons are rendered.
        $nameInput = $client->getWebDriver()->findElement(WebDriverBy::id('liste-name'));
        $this->assertNotNull($nameInput->getAttribute('readonly'), 'Name field should be read-only.');
        $this->assertSelectorNotExists('#right-list .btn-del-item');

        // The "Alle Lieder" picker column is hidden for read-only users.
        $leftWrap = $client->getWebDriver()->findElement(WebDriverBy::id('left-list'));
        $this->assertFalse($leftWrap->isDisplayed(), 'The song picker should be hidden in read-only mode.');
    }

    public function testCannotReachOtherAdminAreas(): void
    {
        $client = $this->loginAsNotenwart();
        $client->request('GET', '/admin/songs');

        // The songs admin table is only rendered for authorised users; a Notenwart
        // is denied and never sees it.
        $this->assertStringNotContainsString(
            'songs-table',
            $client->getWebDriver()->getPageSource(),
            'Notenwart users must not reach the songs admin area.'
        );
    }

    public function testCannotReachAdminDashboard(): void
    {
        $client = $this->loginAsNotenwart();
        $client->request('GET', '/admin');

        $this->assertStringNotContainsString(
            'Administration',
            $client->getWebDriver()->getPageSource(),
            'Notenwart users must not reach the admin dashboard.'
        );
    }
}
