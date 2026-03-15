<?php

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function getGlobals(): array
    {
        return [
            'singerCount' => count($this->userRepository->findAll()) - 1,
        ];
    }
}
