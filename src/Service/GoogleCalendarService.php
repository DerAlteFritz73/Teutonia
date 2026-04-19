<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleCalendarService
{
    private const CALENDAR_ID = 'chor.teutonia@gmail.com';
    private const CACHE_KEY = 'google_calendar_events';
    private const CACHE_TTL = 3600;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private string $apiKey,
    ) {}

    public function getUpcomingEvents(int $maxResults = 15): array
    {
        if (!$this->apiKey) {
            return [];
        }

        return $this->cache->get(self::CACHE_KEY . '_' . $maxResults, function (ItemInterface $item) use ($maxResults): array {
            $item->expiresAfter(self::CACHE_TTL);
            return $this->fetchFromApi($maxResults);
        });
    }

    public function refreshCache(int $maxResults = 15): array
    {
        $this->cache->delete(self::CACHE_KEY);
        return $this->getUpcomingEvents($maxResults);
    }

    private function fetchFromApi(int $maxResults): array
    {
        try {
            $response = $this->httpClient->request('GET',
                'https://www.googleapis.com/calendar/v3/calendars/' . urlencode(self::CALENDAR_ID) . '/events',
                [
                    'query' => [
                        'key' => $this->apiKey,
                        'timeMin' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format(\DateTime::RFC3339),
                        'maxResults' => $maxResults,
                        'singleEvents' => 'true',
                        'orderBy' => 'startTime',
                    ],
                    'timeout' => 10,
                ]
            );

            $data = $response->toArray();
            return $data['items'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('Google Calendar API error: ' . $e->getMessage());
            return [];
        }
    }
}
