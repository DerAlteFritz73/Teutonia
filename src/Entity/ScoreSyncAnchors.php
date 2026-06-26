<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScoreSyncAnchorsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScoreSyncAnchorsRepository::class)]
#[ORM\Table(name: 'score_sync_anchors')]
#[ORM\UniqueConstraint(name: 'uniq_sync', columns: ['song_id', 'pdf_path', 'audio_path'])]
class ScoreSyncAnchors
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SongKeyword::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SongKeyword $song = null;

    #[ORM\Column(length: 500)]
    private string $pdfPath = '';

    #[ORM\Column(length: 500)]
    private string $audioPath = '';

    /** @var array<int, array{t: float, page: int, y: float}> */
    #[ORM\Column(type: 'json')]
    private array $anchors = [];

    public function getId(): ?int { return $this->id; }

    public function getSong(): ?SongKeyword { return $this->song; }
    public function setSong(?SongKeyword $song): static { $this->song = $song; return $this; }

    public function getPdfPath(): string { return $this->pdfPath; }
    public function setPdfPath(string $pdfPath): static { $this->pdfPath = $pdfPath; return $this; }

    public function getAudioPath(): string { return $this->audioPath; }
    public function setAudioPath(string $audioPath): static { $this->audioPath = $audioPath; return $this; }

    public function getAnchors(): array { return $this->anchors; }
    public function setAnchors(array $anchors): static { $this->anchors = $anchors; return $this; }
}
