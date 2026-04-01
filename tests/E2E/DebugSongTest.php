<?php
namespace App\Tests\E2E;
use App\DataFixtures\AppFixtures;

class DebugSongTest extends AbstractE2ETestCase
{
    public function testDebugSongList(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin/songs');
        $client->takeScreenshot('/tmp/debug_songs.png');
        echo "\nBody text:\n" . $client->getCrawler()->text() . "\n";
        $this->assertTrue(true);
    }
}
