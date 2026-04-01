<?php

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

abstract class AbstractE2ETestCase extends PantherTestCase
{
    private static bool $dbReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear browser cookies between tests to prevent state leakage
        if (self::$pantherClient !== null) {
            self::$pantherClient->getCookieJar()->clear();
        }
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$dbReady) {
            self::prepareDatabase();
            self::$dbReady = true;
        }
    }

    private static function prepareDatabase(): void
    {
        $bin = dirname(__DIR__, 2) . '/bin/console';

        // Create DB + schema if they don't exist yet (bypasses broken migrations)
        shell_exec(sprintf('APP_ENV=test php %s doctrine:database:create --if-not-exists 2>&1', $bin));
        shell_exec(sprintf('APP_ENV=test php %s doctrine:schema:update --force --complete --no-interaction 2>&1', $bin));

        // Load fixtures (purges and re-seeds the test DB)
        $output = shell_exec(sprintf('APP_ENV=test php %s doctrine:fixtures:load --no-interaction 2>&1', $bin));
        if (str_contains((string) $output, 'Exception') || str_contains((string) $output, '[ERROR]')) {
            throw new \RuntimeException("Failed to load fixtures:\n$output");
        }
    }

    /**
     * Always use the custom router so the PHP built-in server serves static assets correctly.
     * Adds --no-sandbox and --disable-dev-shm-usage when running inside Docker (as root).
     */
    protected static function createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = []): Client
    {
        $chromeOptions = $options['chromeOptions'] ?? [];

        if (posix_getuid() === 0) {
            $chromeOptions = array_unique(array_merge($chromeOptions, [
                '--no-sandbox',
                '--disable-dev-shm-usage',
            ]));
        }

        return parent::createPantherClient(
            array_merge(['router' => 'router.php'], $options, ['chromeOptions' => $chromeOptions]),
            $kernelOptions,
            $managerOptions
        );
    }

    /**
     * Creates a Panther client and logs in as the given user.
     * The client drives a real headless Chromium browser.
     */
    protected function createLoggedInClient(string $username, string $password): Client
    {
        $client = static::createPantherClient();
        $this->login($client, $username, $password);
        return $client;
    }

    protected function login(Client $client, string $username, string $password): void
    {
        $client->request('GET', '/login');

        // Wait for Stimulus CSRF controller to initialize and set the real token
        $client->waitFor('input[name="_csrf_token"]');

        // Use native browser interaction so Stimulus has time to run
        $client->findElement(WebDriverBy::name('_username'))->sendKeys($username);
        $client->findElement(WebDriverBy::name('_password'))->sendKeys($password);
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Wait for the login form to disappear (navigation complete)
        $client->waitForStaleness('input[name="_password"]');
    }
}
