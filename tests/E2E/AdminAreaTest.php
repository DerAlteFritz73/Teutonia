<?php

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;

/**
 * Tests the admin area (requires ROLE_ADMIN).
 */
class AdminAreaTest extends AbstractE2ETestCase
{
    public function testDashboardShowsStats(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin');

        $this->assertSelectorTextContains('h1', 'Administration');
    }

    public function testSongListShowsFixtureData(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');

        // FooTable hides the table visually until its JS initialises; check the
        // raw page source (server-rendered HTML) instead of visible text.
        // Check the composer, not the song name — testEditExistingSong renames the
        // first song, so "Amazing Grace" may already be "Amazing Grace (bearbeitet)".
        $this->assertStringContainsString('John Newton', $client->getWebDriver()->getPageSource());
    }

    public function testCreateNewSong(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs/new');

        $this->assertSelectorExists('form');

        $client->submitForm('Speichern', [
            'song_keyword[songName]'  => 'O Fortuna',
            'song_keyword[composer]'  => 'Carl Orff',
            'song_keyword[etikett]'   => 'Blau 01',
        ]);

        // Turbo intercepts the form POST as an XHR; calling request() immediately
        // would cancel the in-flight request before the server creates the song.
        // Wait for Turbo to complete its navigation to /admin/songs instead.
        $client->waitFor('#songs-table');

        $this->assertStringContainsString('O Fortuna', $client->getWebDriver()->getPageSource());
    }

    public function testEditExistingSong(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');

        // Wait for at least one data row to appear (gives WebDriver a stable DOM).
        $client->waitFor('#songs-table tbody tr[data-id]');

        // Read the song ID from the data attribute — avoids href-filter issues
        // caused by Panther serializing relative URLs to absolute in getPageSource().
        $row = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $this->assertSelectorExists('form');

        $client->submitForm('Speichern', [
            'song_keyword[songName]' => 'Amazing Grace (bearbeitet)',
        ]);

        $client->waitFor('#songs-table');
        $this->assertStringContainsString('Amazing Grace (bearbeitet)', $client->getWebDriver()->getPageSource());
    }

    public function testKonzerteListLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/konzerte');

        $this->assertSelectorTextContains('body', 'Sommerkonzert 2024');
    }

    public function testUserListLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/users');

        $this->assertSelectorTextContains('body', AppFixtures::MEMBER_USERNAME);
        $this->assertSelectorTextContains('body', AppFixtures::ADMIN_USERNAME);
    }

    public function testStyleListLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/styles');

        $this->assertSelectorTextContains('body', 'Gospel');
    }
}
