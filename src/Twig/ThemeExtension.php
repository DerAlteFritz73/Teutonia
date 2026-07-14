<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Resolves the active visual theme for the site (see public/css/app.css).
 *
 * The SITE_THEME env var may name a fixed theme (warm, forest, navy, mono, plum,
 * spring, summer, autumn, winter) or the special value "auto", which picks the
 * theme matching the current season. Because the function runs per request, an
 * "auto" site changes look as the seasons turn — no redeploy needed.
 */
class ThemeExtension extends AbstractExtension
{
    private const THEMES = [
        'warm', 'forest', 'navy', 'mono', 'plum',
        'spring', 'summer', 'autumn', 'winter',
    ];

    public function __construct(private readonly string $configuredTheme = 'warm')
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_theme', [$this, 'activeTheme']),
        ];
    }

    public function activeTheme(): string
    {
        $theme = strtolower(trim($this->configuredTheme));

        if (in_array($theme, ['auto', 'season', 'seasonal'], true)) {
            return $this->currentSeason();
        }

        return in_array($theme, self::THEMES, true) ? $theme : 'warm';
    }

    /**
     * Meteorological season for the Northern Hemisphere (Willstätt), by month:
     * spring Mar–May, summer Jun–Aug, autumn Sep–Nov, winter Dec–Feb.
     */
    public function currentSeason(?\DateTimeInterface $now = null): string
    {
        $month = (int) ($now ?? new \DateTimeImmutable())->format('n');

        return match (true) {
            $month >= 3 && $month <= 5 => 'spring',
            $month >= 6 && $month <= 8 => 'summer',
            $month >= 9 && $month <= 11 => 'autumn',
            default => 'winter',
        };
    }
}
