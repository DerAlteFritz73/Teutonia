<?php

namespace App\Entity;

use App\Repository\LiederlisteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LiederlisteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Liederliste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /**
     * Each item: {type:'song'|'custom', songId?:int, composer:string, title:string, duration?:string}
     */
    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getItems(): array { return $this->items; }
    public function setItems(array $items): static { $this->items = $items; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getTotalDuration(): string
    {
        $total = 0;
        foreach ($this->items as $item) {
            if (!empty($item['duration'])) {
                $parts = explode(':', $item['duration']);
                $total += (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
            }
        }
        return sprintf('%d:%02d', intdiv($total, 60), $total % 60);
    }
}
