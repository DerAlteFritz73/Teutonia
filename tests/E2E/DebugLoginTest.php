<?php
namespace App\Tests\E2E;

class DebugLoginTest extends AbstractE2ETestCase
{
    public function testDebugLogin(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/login');
        sleep(2);

        $csrfBefore = $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_csrf_token'))->getAttribute('value');
        echo "\nCSRF before fill: '$csrfBefore' (" . strlen($csrfBefore) . " chars)\n";

        $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_username'))->sendKeys('testuser');
        $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_password'))->sendKeys('Test1234!');
        $client->findElement(\Facebook\WebDriver\WebDriverBy::cssSelector('button[type="submit"]'))->click();

        sleep(3);
        echo "URL after login: " . $client->getCurrentURL() . "\n";
        $client->takeScreenshot('/tmp/after_login.png');

        $this->assertTrue(true);
    }
}
