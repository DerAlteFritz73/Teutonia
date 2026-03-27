<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'song_link')]
class SongLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SongKeyword::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SongKeyword $song = null;

    #[ORM\Column(length: 500)]
    private string $url = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int { return $this->id; }

    public function getSong(): ?SongKeyword { return $this->song; }
    public function setSong(?SongKeyword $song): static { $this->song = $song; return $this; }

    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): static { $this->url = $url; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }
}
