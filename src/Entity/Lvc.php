<?php

namespace App\Entity;

use App\Repository\LvcRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LvcRepository::class)]
#[ORM\Index(name: 'idx_lvc_created_at', columns: ['created_at'])]
class Lvc
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')] // Il faudra utiliser 'IDENTITY' avec DBAL 4
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?float $closing = null;

    #[ORM\Column]
    private ?float $opening = null;

    #[ORM\Column]
    private ?float $higher = null;

    #[ORM\Column]
    private ?float $lower = null;

    /**
     * @var Collection<int, LastHigh>
     */
    #[ORM\OneToMany(targetEntity: LastHigh::class, mappedBy: 'dailyLvc')]
    private Collection $lastHigher;

    public function __construct()
    {
        $this->lastHigher = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClosing(): ?float
    {
        return $this->closing;
    }

    public function setClosing(float $closing): static
    {
        $this->closing = $closing;

        return $this;
    }

    public function getOpening(): ?float
    {
        return $this->opening;
    }

    public function setOpening(float $opening): static
    {
        $this->opening = $opening;

        return $this;
    }

    public function getHigher(): ?float
    {
        return $this->higher;
    }

    public function setHigher(float $higher): static
    {
        $this->higher = $higher;

        return $this;
    }

    public function getLower(): ?float
    {
        return $this->lower;
    }

    public function setLower(float $lower): static
    {
        $this->lower = $lower;

        return $this;
    }

    /**
     * @return Collection<int, LastHigh>
     */
    public function getLastHigher(): Collection
    {
        return $this->lastHigher;
    }

    public function addLastHigher(LastHigh $lastHigher): static
    {
        if (!$this->lastHigher->contains($lastHigher)) {
            $this->lastHigher->add($lastHigher);
            $lastHigher->setDailyLvc($this);
        }

        return $this;
    }

    public function removeLastHigher(LastHigh $lastHigher): static
    {
        if ($this->lastHigher->removeElement($lastHigher)) {
            // set the owning side to null (unless already changed)
            if ($lastHigher->getDailyLvc() === $this) {
                $lastHigher->setDailyLvc(null);
            }
        }

        return $this;
    }
}
