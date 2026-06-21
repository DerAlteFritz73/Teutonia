<?php

namespace App\Entity;

use App\Repository\SongKeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTimeInterface;

#[ORM\Entity(repositoryClass: SongKeywordRepository::class)]
#[ORM\Table(name: 'song')]
class SongKeyword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $songName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $composer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $arrangeur = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $keywords = [];

    #[ORM\Column(name: 'etikett', length: 20, nullable: true)]
    private ?string $etikett = null;

    #[ORM\Column(length: 100)]
    private ?string $folder = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $dropboxlink = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isAktuelleProben = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $aktuelleDropboxlink = null;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'songName' => 'ASC'])]
    private Collection $children;

    #[ORM\ManyToMany(targetEntity: Style::class, inversedBy: 'songKeywords')]
    #[ORM\JoinTable(name: 'song_style',
        joinColumns: [new ORM\JoinColumn(name: 'song_keyword_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'style_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    private Collection $styles;

    #[ORM\ManyToMany(targetEntity: Konzert::class, mappedBy: 'songs')]
    private Collection $konzerte;

    #[ORM\OneToMany(mappedBy: 'song', targetEntity: SongLink::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $links;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->styles   = new ArrayCollection();
        $this->konzerte = new ArrayCollection();
        $this->links    = new ArrayCollection();
    }

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

    public function setComposer(?string $composer): static
    {
        $this->composer = $composer;
        return $this;
    }

    public function getArrangeur(): ?string
    {
        return $this->arrangeur;
    }

    public function setArrangeur(?string $arrangeur): static
    {
        $this->arrangeur = $arrangeur;
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

    public function getEtikett(): ?string
    {
        return $this->etikett;
    }

    public function setEtikett(?string $etikett): static
    {
        $this->etikett = $etikett;
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

    public function getDropboxlink(): ?string
    {
        return $this->dropboxlink;
    }

    public function setDropboxlink(?string $dropboxlink): static
    {
        $this->dropboxlink = $dropboxlink;
        return $this;
    }

    public function isAktuelleProben(): bool { return $this->isAktuelleProben; }
    public function setIsAktuelleProben(bool $isAktuelleProben): static { $this->isAktuelleProben = $isAktuelleProben; return $this; }

    public function getAktuelleDropboxlink(): ?string { return $this->aktuelleDropboxlink; }
    public function setAktuelleDropboxlink(?string $aktuelleDropboxlink): static { $this->aktuelleDropboxlink = $aktuelleDropboxlink; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): static { $this->parent = $parent; return $this; }
    public function getChildren(): Collection { return $this->children; }
    public function hasChildren(): bool { return !$this->children->isEmpty(); }
    public function isMovement(): bool { return $this->parent !== null; }

    public function getStyles(): Collection
    {
        return $this->styles;
    }

    public function getKonzerte(): Collection
    {
        return $this->konzerte;
    }

    public function getLatestKonzertDate(): ?\DateTimeInterface
    {
        $dates = $this->konzerte
            ->filter(fn($k) => $k->getDate() !== null)
            ->map(fn($k) => $k->getDate())
            ->toArray();

        return empty($dates) ? null : max($dates);
    }

    public function getLinks(): Collection { return $this->links; }

    public function addLink(SongLink $link): static
    {
        if (!$this->links->contains($link)) {
            $this->links->add($link);
            $link->setSong($this);
        }
        return $this;
    }

    public function removeLink(SongLink $link): static
    {
        $this->links->removeElement($link);
        return $this;
    }

    public function addStyle(Style $style): static
    {
        if (!$this->styles->contains($style)) {
            $this->styles->add($style);
        }
        return $this;
    }

    public function removeStyle(Style $style): static
    {
        $this->styles->removeElement($style);
        return $this;
    }
}
