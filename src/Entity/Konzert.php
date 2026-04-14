<?php

namespace App\Entity;

use App\Repository\KonzertRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KonzertRepository::class)]
class Konzert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $customSongs = [];

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $songOrder = [];

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $videoLinks = [];

    #[ORM\ManyToMany(targetEntity: SongKeyword::class, inversedBy: 'konzerte')]
    #[ORM\JoinTable(name: 'konzert_song',
        joinColumns: [new ORM\JoinColumn(name: 'konzert_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'song_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    private Collection $songs;

    public function __construct()
    {
        $this->songs = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): static { $this->date = $date; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): static { $this->location = $location; return $this; }

    public function getCustomSongs(): array { return $this->customSongs; }
    public function setCustomSongs(array $customSongs): static { $this->customSongs = $customSongs; return $this; }

    public function getSongOrder(): array { return $this->songOrder; }
    public function setSongOrder(array $songOrder): static { $this->songOrder = $songOrder; return $this; }

    public function getVideoLinks(): array { return $this->videoLinks; }
    public function setVideoLinks(array $videoLinks): static { $this->videoLinks = $videoLinks; return $this; }

    public function getSongs(): Collection { return $this->songs; }

    public function addSong(SongKeyword $song): static
    {
        if (!$this->songs->contains($song)) {
            $this->songs->add($song);
        }
        return $this;
    }

    public function removeSong(SongKeyword $song): static
    {
        $this->songs->removeElement($song);
        return $this;
    }

    /**
     * Returns the full ordered song list exactly as the edit-page JS does:
     * 1. Entries in songOrder (regular songs then custom songs, interleaved)
     * 2. Linked songs not referenced in songOrder
     * 3. Custom songs not referenced in songOrder
     *
     * Each item: ['type' => 'song'|'custom', 'composer' => string, 'title' => string]
     */
    public function getOrderedSongList(): array
    {
        $songMap = [];
        foreach ($this->songs as $song) {
            $songMap[$song->getId()] = $song;
        }

        $result       = [];
        $usedSongIds  = [];
        $usedCustIdxs = [];

        foreach ($this->songOrder as $entry) {
            if (is_int($entry) || (is_string($entry) && ctype_digit($entry))) {
                $id = (int) $entry;
                if (isset($songMap[$id])) {
                    $song          = $songMap[$id];
                    $result[]      = ['type' => 'song', 'composer' => (string) $song->getComposer(), 'title' => (string) $song->getSongName(), 'videoLink' => $this->videoLinks[(string) $id] ?? ''];
                    $usedSongIds[] = $id;
                }
            } elseif (is_string($entry) && isset($entry[0]) && $entry[0] === 'c') {
                $idx = (int) substr($entry, 1);
                if (isset($this->customSongs[$idx])) {
                    $cs             = $this->customSongs[$idx];
                    $result[]       = ['type' => 'custom', 'composer' => (string) ($cs['composer'] ?? ''), 'title' => (string) ($cs['title'] ?? ''), 'videoLink' => $this->videoLinks[$entry] ?? ''];
                    $usedCustIdxs[] = $idx;
                }
            }
        }

        foreach ($this->songs as $song) {
            if (!in_array($song->getId(), $usedSongIds, true)) {
                $result[] = ['type' => 'song', 'composer' => (string) $song->getComposer(), 'title' => (string) $song->getSongName(), 'videoLink' => $this->videoLinks[(string) $song->getId()] ?? ''];
            }
        }

        foreach ($this->customSongs as $idx => $cs) {
            if (!in_array($idx, $usedCustIdxs, true)) {
                $result[] = ['type' => 'custom', 'composer' => (string) ($cs['composer'] ?? ''), 'title' => (string) ($cs['title'] ?? ''), 'videoLink' => $this->videoLinks['c' . $idx] ?? ''];
            }
        }

        return $result;
    }
}
