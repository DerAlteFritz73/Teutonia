<?php

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;
use Facebook\WebDriver\WebDriverBy;

/**
 * Tests authentication flows: login, logout, access control.
 */
class AuthTest extends AbstractE2ETestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/login');

        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/login');
        $client->findElement(WebDriverBy::name('_username'))->sendKeys('nobody');
        $client->findElement(WebDriverBy::name('_password'))->sendKeys('wrongpassword');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $client->waitFor('.alert-danger');
        $this->assertSelectorExists('input[name="_username"]', 'Should stay on login page');
        $this->assertSelectorExists('.alert-danger', 'Should show an error message');
    }

    public function testMemberLoginRedirectsToDashboard(): void
    {
        $client = static::createPantherClient();
        $this->login($client, AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);

        $this->assertStringContainsString('/mitglieder', $client->getCurrentURL());
        $this->assertSelectorTextContains('h1', 'Willkommen');
    }

    public function testAdminLoginRedirectsToDashboard(): void
    {
        $client = static::createPantherClient();
        $this->login($client, AppFixtures::ADMIN_USERNAME, AppFixtures::PASSWORD);

        $this->assertStringContainsString('/mitglieder', $client->getCurrentURL());
    }

    public function testLogoutRedirectsToHome(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/logout');

        $this->assertStringNotContainsString('/mitglieder', $client->getCurrentURL());
    }

    public function testUserDropdownExpandsOnClick(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);

        $client->findElement(WebDriverBy::cssSelector('.nav-item.dropdown .dropdown-toggle'))->click();
        $client->waitFor('.dropdown-menu.show');

        $this->assertSelectorExists('.dropdown-menu.show');
    }

    public function testLogoutViaDropdownAfterTurboNavigation(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);

        // Navigate via Turbo to a second page, then use the dropdown to log out
        $client->request('GET', '/');
        $client->findElement(WebDriverBy::cssSelector('.nav-item.dropdown .dropdown-toggle'))->click();
        $client->waitFor('.dropdown-menu.show');
        $client->findElement(WebDriverBy::linkText('Abmelden'))->click();

        $client->waitFor('body');
        $this->assertStringNotContainsString('/mitglieder', $client->getCurrentURL());
        $this->assertSelectorNotExists('.nav-item.dropdown');
    }

    public function testMemberAreaRedirectsUnauthenticatedUser(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/mitglieder');

        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testAdminAreaRedirectsUnauthenticatedUser(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/admin');

        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testMemberCannotAccessAdminArea(): void
    {
        $client = $this->createLoggedInClient(AppFixtures::MEMBER_USERNAME, AppFixtures::PASSWORD);
        $client->request('GET', '/admin');

        // Member should be denied: either redirected to /login or shown a 403 — either way no admin dashboard
        $this->assertSelectorTextNotContains('h1', 'Administration');
    }
}
