<?php

namespace App\Entity;

use App\Repository\SongSuggestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SongSuggestionRepository::class)]
class SongSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Bitte geben Sie einen Songtitel ein")]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $artist = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(message: 'Bitte geben Sie eine gültige URL ein')]
    #[Assert\Length(max: 500)]
    private ?string $link = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submittedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: SongSuggestionLike::class, mappedBy: 'suggestion', orphanRemoval: true)]
    private Collection $likes;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->likes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function setArtist(?string $artist): static
    {
        $this->artist = $artist;
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

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, SongSuggestionLike>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(SongSuggestionLike $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setSuggestion($this);
        }

        return $this;
    }

    public function removeLike(SongSuggestionLike $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getSuggestion() === $this) {
                $like->setSuggestion(null);
            }
        }

        return $this;
    }

    public function getLikeCount(): int
    {
        return $this->likes->count();
    }

    public function isLikedByUser(User $user): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getUser() === $user) {
                return true;
            }
        }
        return false;
    }
}
