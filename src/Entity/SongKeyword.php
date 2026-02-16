<?php

namespace App\Entity;

use App\Repository\SongKeywordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongKeywordRepository::class)]
class SongKeyword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $songName = null;

    #[ORM\Column(length: 255)]
    private ?string $composer = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $keywords = [];

    #[ORM\Column(length: 100)]
    private ?string $folder = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSongName(): ?string
    {
        return $this->songName;
    }

    public function setSongName(string $songName): static
    {
        $this->songName = $songName;
        return $this;
    }

    public function getComposer(): ?string
    {
        return $this->composer;
    }

    public function setComposer(string $composer): static
    {
        $this->composer = $composer;
        return $this;
    }

    public function getKeywords(): ?array
    {
        return $this->keywords ?? [];
    }

    public function setKeywords(?array $keywords): static
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function getFolder(): ?string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): static
    {
        $this->folder = $folder;
        return $this;
    }
}
