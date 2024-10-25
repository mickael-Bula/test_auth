<?php

namespace App\Entity;

use App\Repository\CacRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CacRepository::class)]
class Cac
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?float $closing = null;

    #[ORM\Column]
    private ?float $opening = null;

    #[ORM\Column]
    private ?float $lower = null;

    #[ORM\Column]
    private ?float $higher = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'lastCacUpdated')]
    private Collection $users;

    /**
     * @var Collection<int, LastHigh>
     */
    #[ORM\OneToMany(targetEntity: LastHigh::class, mappedBy: 'dailyCac')]
    private Collection $lastHigher;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    public function getLower(): ?float
    {
        return $this->lower;
    }

    public function setLower(float $lower): static
    {
        $this->lower = $lower;

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

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setLastCacUpdated($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getLastCacUpdated() === $this) {
                $user->setLastCacUpdated(null);
            }
        }

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
            $lastHigher->setDailyCac($this);
        }

        return $this;
    }

    public function removeLastHigher(LastHigh $lastHigher): static
    {
        if ($this->lastHigher->removeElement($lastHigher)) {
            // set the owning side to null (unless already changed)
            if ($lastHigher->getDailyCac() === $this) {
                $lastHigher->setDailyCac(null);
            }
        }

        return $this;
    }
}
