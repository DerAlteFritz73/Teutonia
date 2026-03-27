<?php

namespace App\Entity;

use App\Repository\StyleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;

#[ORM\Entity(repositoryClass: StyleRepository::class)]
class Style
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $color2 = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $image = null;

    #[ORM\ManyToMany(targetEntity: SongKeyword::class, mappedBy: 'styles')]
    private Collection $songKeywords;

    #[ORM\ManyToMany(targetEntity: SongKeyword::class)]
    #[JoinTable(name: 'style_repertoire_songs',
        joinColumns: [new JoinColumn(name: 'style_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new JoinColumn(name: 'song_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    private Collection $repertoireSongs;

    public function __construct()
    {
        $this->songKeywords   = new ArrayCollection();
        $this->repertoireSongs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getColor2(): ?string
    {
        return $this->color2;
    }

    public function setColor2(?string $color2): static
    {
        $this->color2 = $color2;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getSongKeywords(): Collection
    {
        return $this->songKeywords;
    }

    public function getRepertoireSongs(): Collection
    {
        return $this->repertoireSongs;
    }

    public function addRepertoireSong(SongKeyword $song): static
    {
        if (!$this->repertoireSongs->contains($song)) {
            $this->repertoireSongs->add($song);
        }
        return $this;
    }

    public function removeRepertoireSong(SongKeyword $song): static
    {
        $this->repertoireSongs->removeElement($song);
        return $this;
    }
}
