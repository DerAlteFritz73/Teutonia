<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paragraph = null;

    #[ORM\Column(length: 50)]
    private ?string $layout = 'single_row';

    #[ORM\Column]
    private ?bool $isMain = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 100)]
    private ?string $page = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fontTitle = 'default';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fontTitleSize = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontTitleBold = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontTitleItalic = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontTitleUnderline = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fontSubtitle = 'default';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fontSubtitleSize = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontSubtitleBold = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontSubtitleItalic = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontSubtitleUnderline = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fontParagraph = 'default';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fontParagraphSize = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontParagraphBold = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontParagraphItalic = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $fontParagraphUnderline = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $position = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $imageRotation = 0;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $date = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getParagraph(): ?string
    {
        return $this->paragraph;
    }

    public function setParagraph(?string $paragraph): static
    {
        $this->paragraph = $paragraph;
        return $this;
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): static
    {
        $this->layout = $layout;
        return $this;
    }

    public function isMain(): ?bool
    {
        return $this->isMain;
    }

    public function setIsMain(bool $isMain): static
    {
        $this->isMain = $isMain;
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

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setPage(string $page): static
    {
        $this->page = $page;
        return $this;
    }

    public function getFontTitle(): ?string
    {
        return $this->fontTitle;
    }

    public function setFontTitle(?string $fontTitle): static
    {
        $this->fontTitle = $fontTitle;
        return $this;
    }

    public function getFontSubtitle(): ?string
    {
        return $this->fontSubtitle;
    }

    public function setFontSubtitle(?string $fontSubtitle): static
    {
        $this->fontSubtitle = $fontSubtitle;
        return $this;
    }

    public function getFontParagraph(): ?string
    {
        return $this->fontParagraph;
    }

    public function setFontParagraph(?string $fontParagraph): static
    {
        $this->fontParagraph = $fontParagraph;
        return $this;
    }

    public function getFontTitleSize(): ?int
    {
        return $this->fontTitleSize;
    }

    public function setFontTitleSize(?int $fontTitleSize): static
    {
        $this->fontTitleSize = $fontTitleSize;
        return $this;
    }

    public function getFontSubtitleSize(): ?int
    {
        return $this->fontSubtitleSize;
    }

    public function setFontSubtitleSize(?int $fontSubtitleSize): static
    {
        $this->fontSubtitleSize = $fontSubtitleSize;
        return $this;
    }

    public function getFontParagraphSize(): ?int
    {
        return $this->fontParagraphSize;
    }

    public function setFontParagraphSize(?int $fontParagraphSize): static
    {
        $this->fontParagraphSize = $fontParagraphSize;
        return $this;
    }

    public function isFontTitleBold(): ?bool { return $this->fontTitleBold; }
    public function setFontTitleBold(?bool $fontTitleBold): static { $this->fontTitleBold = $fontTitleBold; return $this; }

    public function isFontTitleItalic(): ?bool { return $this->fontTitleItalic; }
    public function setFontTitleItalic(?bool $fontTitleItalic): static { $this->fontTitleItalic = $fontTitleItalic; return $this; }

    public function isFontTitleUnderline(): ?bool { return $this->fontTitleUnderline; }
    public function setFontTitleUnderline(?bool $fontTitleUnderline): static { $this->fontTitleUnderline = $fontTitleUnderline; return $this; }

    public function isFontSubtitleBold(): ?bool { return $this->fontSubtitleBold; }
    public function setFontSubtitleBold(?bool $fontSubtitleBold): static { $this->fontSubtitleBold = $fontSubtitleBold; return $this; }

    public function isFontSubtitleItalic(): ?bool { return $this->fontSubtitleItalic; }
    public function setFontSubtitleItalic(?bool $fontSubtitleItalic): static { $this->fontSubtitleItalic = $fontSubtitleItalic; return $this; }

    public function isFontSubtitleUnderline(): ?bool { return $this->fontSubtitleUnderline; }
    public function setFontSubtitleUnderline(?bool $fontSubtitleUnderline): static { $this->fontSubtitleUnderline = $fontSubtitleUnderline; return $this; }

    public function isFontParagraphBold(): ?bool { return $this->fontParagraphBold; }
    public function setFontParagraphBold(?bool $fontParagraphBold): static { $this->fontParagraphBold = $fontParagraphBold; return $this; }

    public function isFontParagraphItalic(): ?bool { return $this->fontParagraphItalic; }
    public function setFontParagraphItalic(?bool $fontParagraphItalic): static { $this->fontParagraphItalic = $fontParagraphItalic; return $this; }

    public function isFontParagraphUnderline(): ?bool { return $this->fontParagraphUnderline; }
    public function setFontParagraphUnderline(?bool $fontParagraphUnderline): static { $this->fontParagraphUnderline = $fontParagraphUnderline; return $this; }

    public function getPosition(): ?int { return $this->position; }
    public function setPosition(?int $position): static { $this->position = $position; return $this; }

    public function getImageRotation(): ?int { return $this->imageRotation; }
    public function setImageRotation(?int $imageRotation): static { $this->imageRotation = $imageRotation; return $this; }

    public function getDate(): ?string { return $this->date; }
    public function setDate(?string $date): static { $this->date = $date; return $this; }
}
