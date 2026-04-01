<?php

namespace App\Tests\E2E;

/**
 * Tests that public pages are accessible without authentication.
 */
class PublicPagesTest extends AbstractE2ETestCase
{
    /**
     * @dataProvider publicRouteProvider
     */
    public function testPublicPageLoads(string $path, string $expectedText): void
    {
        $client = static::createPantherClient();
        $client->request('GET', $path);

        $this->assertSelectorTextContains('body', $expectedText);
    }

    public static function publicRouteProvider(): array
    {
        return [
            'home'           => ['/', 'Teutonia'],
            'unser-chor'     => ['/unser-chor', 'Chor'],
            'konzerte'       => ['/konzerte-und-aktivitaeten', 'Konzert'],
            'chorproben'     => ['/chorproben', 'Chorproben'],
            'repertoire'     => ['/unser-repertoire', 'Repertoire'],
        ];
    }

    public function testLoginPageDoesNotRequireAuth(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/login');

        $this->assertSelectorExists('form');
    }

    public function testForgotPasswordPageLoads(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/passwort-vergessen');

        $this->assertSelectorExists('input[name="identifier"]');
    }
}
