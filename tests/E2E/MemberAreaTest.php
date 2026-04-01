<?php

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;

/**
 * Tests the member area (requires ROLE_USER).
 */
class MemberAreaTest extends AbstractE2ETestCase
{
    public function testDashboardShowsWelcomeMessage(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder');

        $this->assertSelectorTextContains('h1', 'Willkommen');
    }

    public function testDashboardLinksToMainSections(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder');

        $this->assertSelectorExists('a[href*="aktuelle-proben"]');
        $this->assertSelectorExists('a[href*="konzerte"]');
        $this->assertSelectorExists('a[href*="ideenkiste"]');
    }

    public function testProbenPageLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/aktuelle-proben');

        $this->assertSelectorTextContains('h1', 'Aktuelle Proben');
    }

    public function testKonzertePageLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/konzerte');

        $this->assertSelectorTextContains('body', 'Sommerkonzert 2024');
    }

    public function testIdeenkisteListsExistingSuggestions(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/ideenkiste');

        $this->assertSelectorTextContains('body', 'Hallelujah');
    }

    public function testSubmitNewSongSuggestion(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/ideenkiste/neu');

        $this->assertSelectorExists('form');

        $client->submitForm('Vorschlag einreichen', [
            'song_suggestion[title]'       => 'Ave Maria',
            'song_suggestion[artist]'      => 'Franz Schubert',
            'song_suggestion[description]' => 'Wunderschöne Melodie',
            'song_suggestion[link]'        => 'https://www.youtube.com/watch?v=test',
        ]);

        // Should redirect back to the list after successful submission
        $this->assertStringContainsString('/ideenkiste', $client->getCurrentURL());
        $this->assertSelectorTextContains('body', 'Ave Maria');
    }

    public function testGoogleKalenderPageLoads(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/mitglieder/google-kalender');

        $this->assertSelectorTextContains('h1', 'Kalender');
    }
}
