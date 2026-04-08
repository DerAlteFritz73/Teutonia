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

    public function testNewSongFormShowsNeuesLiedHeading(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs/new');

        $this->assertSelectorTextContains('h1', 'Neues Lied');
    }

    public function testEditSongFormShowsSongNameInHeading(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('h1');

        // Edit form shows the song name, not the generic "Neues Lied" label
        $h1Text = $client->getWebDriver()
            ->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('h1'))
            ->getText();
        $this->assertStringNotContainsString('Neues Lied', $h1Text);
        $this->assertNotEmpty($h1Text);
    }

    public function testSaveButtonDisabledWithNoChanges(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('form[data-unsaved-check]');

        // The JS disables the save button until the form is dirty
        $saveBtn = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]')
        );
        $this->assertFalse($saveBtn->isEnabled(), 'Save button should be disabled when no changes have been made.');
    }

    public function testSaveButtonEnabledAfterChange(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('form[data-unsaved-check]');

        // Type something in the composer field to mark the form dirty
        $composerInput = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song_keyword_composer')
        );
        $composerInput->sendKeys('x');

        $saveBtn = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]')
        );
        $this->assertTrue($saveBtn->isEnabled(), 'Save button should be enabled once the form is changed.');
    }

    public function testCancelButtonDisabledWithNoChanges(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('form[data-unsaved-check]');

        $cancelBtn = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('[data-unsaved-cancel]')
        );
        $ariaDisabled = $cancelBtn->getAttribute('aria-disabled');
        $this->assertEquals('true', $ariaDisabled, 'Cancel button should have aria-disabled="true" when no changes have been made.');
    }

    public function testDeleteSong(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);

        // Create a song to delete
        $client->request('GET', '/admin/songs/new');
        $client->submitForm('Speichern', [
            'song_keyword[songName]' => 'Zum Löschen',
            'song_keyword[composer]' => 'Temp Composer',
        ]);
        $client->waitFor('#songs-table');

        // Find its row by name in the page source
        $this->assertStringContainsString('Zum Löschen', $client->getWebDriver()->getPageSource());

        // Find the song's edit link and navigate to the edit page
        $client->waitFor('#songs-table tbody tr[data-id]');
        $rows = $client->getWebDriver()->findElements(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = null;
        foreach ($rows as $row) {
            // Look for the row that contains "Zum Löschen" in the page source
            if (str_contains($row->getText(), 'Zum Löschen')) {
                $songId = $row->getAttribute('data-id');
                break;
            }
        }
        if (!$songId) {
            $this->markTestSkipped('Could not find the newly created song row.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('button.btn-outline-danger');

        // Accept the confirm dialog and submit the delete form via JS
        $client->getWebDriver()->executeScript(
            'document.getElementById("song-delete-form").submit();'
        );
        $client->waitFor('#songs-table');

        $this->assertStringNotContainsString('Zum Löschen', $client->getWebDriver()->getPageSource());
    }

    public function testAddYouTubeLink(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#add-link-btn');

        // Click "Link hinzufügen"
        $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('add-link-btn')
        )->click();

        // Fill the URL field in the newly added (last) link row
        $client->waitFor('#song-links-container .link-row:last-child input');
        $urlInput = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song-links-container .link-row:last-child input')
        );
        $urlInput->sendKeys('https://www.youtube.com/watch?v=test123');

        $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]')
        )->click();

        $client->waitFor('#songs-table');

        // Navigate back to edit and verify the link was saved
        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $this->assertStringContainsString('test123', $client->getWebDriver()->getPageSource());
    }

    public function testRemoveYouTubeLink(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        // First add a link
        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#add-link-btn');
        $client->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::id('add-link-btn'))->click();
        $client->waitFor('#song-links-container .link-row:last-child input');
        $urlInput = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song-links-container .link-row:last-child input')
        );
        $urlInput->sendKeys('https://www.youtube.com/watch?v=toremove');
        $client->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]'))->click();
        $client->waitFor('#songs-table');

        // Now open the edit form again and remove the link
        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#song-links-container .link-row');
        $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song-links-container .link-row .remove-link')
        )->click();

        // The row should be removed from the DOM immediately
        $rows = $client->getWebDriver()->findElements(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song-links-container .link-row')
        );
        $this->assertEmpty($rows, 'Link row should be removed from DOM after clicking remove.');

        // Link removal is a DOM operation — it does not fire input/change events, so the
        // unsaved-changes tracker won't enable the save button automatically. Mark the form
        // dirty explicitly before saving.
        $client->getWebDriver()->executeScript('window.markFormDirty()');

        // Save and verify link is gone
        $client->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]'))->click();
        $client->waitFor('#songs-table');

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $this->assertStringNotContainsString('toremove', $client->getWebDriver()->getPageSource());
    }

    public function testStyleCheckboxSavesAndPersists(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('[id^="song_keyword_styles_"]');

        // Check the first style checkbox
        $checkbox = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('[id^="song_keyword_styles_"]')
        );
        if (!$checkbox->isSelected()) {
            $checkbox->click();
        }

        $client->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]'))->click();
        $client->waitFor('#songs-table');

        // Reload the edit form and verify the checkbox is still checked
        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('[id^="song_keyword_styles_"]');

        $checkbox = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('[id^="song_keyword_styles_"]')
        );
        $this->assertTrue($checkbox->isSelected(), 'Style checkbox should remain checked after saving.');
    }

    public function testIsAktuelleProbenCheckboxSavesAndPersists(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#song_keyword_isAktuelleProben');

        $checkbox = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_isAktuelleProben')
        );
        $wasChecked = $checkbox->isSelected();
        if (!$wasChecked) {
            $checkbox->click();
        }

        $client->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]'))->click();
        $client->waitFor('#songs-table');

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#song_keyword_isAktuelleProben');

        $checkbox = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_isAktuelleProben')
        );
        $this->assertTrue($checkbox->isSelected(), '"Aktuelle Proben" checkbox should remain checked after saving.');
    }

    public function testDropboxLinkSavesAndPersists(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->waitFor('#songs-table tbody tr[data-id]');

        $row    = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#songs-table tbody tr[data-id]')
        );
        $songId = $row->getAttribute('data-id');
        if (!$songId) {
            $this->markTestSkipped('No songs found in table.');
        }

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#song_keyword_dropboxlink');

        $dropboxInput = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_dropboxlink')
        );
        $dropboxInput->clear();
        $dropboxInput->sendKeys('/Chorgemeinschaft Teutonia/Noten/Test');

        $client->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]'))->click();
        $client->waitFor('#songs-table');

        $client->request('GET', '/admin/songs/' . $songId . '/edit');
        $client->waitFor('#song_keyword_dropboxlink');

        $val = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_dropboxlink')
        )->getAttribute('value');
        $this->assertEquals('/Chorgemeinschaft Teutonia/Noten/Test', $val, 'Dropbox link should be persisted after saving.');
    }

    public function testParentSearchFiltersDropdownOptions(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs/new');
        $client->waitFor('#parent-search');

        $searchInput = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('parent-search')
        );
        $select = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_parent')
        );

        // Count total visible options before filtering
        $totalOptions = count($client->getWebDriver()->findElements(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song_keyword_parent option:not([hidden])')
        ));

        // Type a string that should match "Amazing Grace" but not other songs
        $searchInput->sendKeys('Amazing');

        // Wait briefly for JS to apply the filter
        usleep(300000);

        $visibleOptions = count($client->getWebDriver()->findElements(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song_keyword_parent option:not([hidden])')
        ));

        $this->assertLessThan($totalOptions, $visibleOptions, 'Parent search should hide non-matching options.');
        $this->assertGreaterThan(0, $visibleOptions, 'At least one matching option should remain visible.');
    }

    public function testAutoFillComposerFromParentSelection(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs/new');
        $client->waitFor('#song_keyword_parent');

        $select = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_parent')
        );

        // Find the option for "Amazing Grace" (has composer "John Newton")
        $options = $client->getWebDriver()->findElements(
            \Facebook\WebDriver\WebDriverBy::cssSelector('#song_keyword_parent option')
        );

        $targetOption = null;
        foreach ($options as $option) {
            if (str_contains($option->getText(), 'Amazing Grace')) {
                $targetOption = $option;
                break;
            }
        }

        if (!$targetOption) {
            $this->markTestSkipped('Could not find "Amazing Grace" in parent select options.');
        }

        // Select the parent song via JS to trigger the change event
        $value = $targetOption->getAttribute('value');
        $client->getWebDriver()->executeScript(
            'var sel = document.getElementById("song_keyword_parent");
             sel.value = arguments[0];
             sel.dispatchEvent(new Event("change"));',
            [$value]
        );

        usleep(200000);

        $composerVal = $client->getWebDriver()->findElement(
            \Facebook\WebDriver\WebDriverBy::id('song_keyword_composer')
        )->getAttribute('value');

        $this->assertEquals('John Newton', $composerVal, 'Composer field should be auto-filled from the selected parent song.');
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
