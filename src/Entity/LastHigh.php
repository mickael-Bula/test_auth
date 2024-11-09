<?php

namespace App\Entity;

use App\Repository\LastHighRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LastHighRepository::class)]
class LastHigh
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["position_read"])]
    private ?int $id = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'higher')]
    private Collection $users;

    #[ORM\Column]
    private ?float $higher = null;

    #[ORM\Column]
    #[Groups(["position_read"])]
    private ?float $buyLimit = null;

    #[ORM\Column]
    private ?float $lvcHigher = null;

    #[ORM\Column]
    private ?float $lvcBuyLimit = null;

    #[ORM\ManyToOne(inversedBy: 'lastHigher')]
    private ?Cac $dailyCac = null;

    #[ORM\ManyToOne(inversedBy: 'lastHigher')]
    private ?Lvc $dailyLvc = null;

    /**
     * @var Collection<int, Position>
     */
    #[ORM\OneToMany(targetEntity: Position::class, mappedBy: 'buyLimit')]
    private Collection $positions;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
            $user->setHigher($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getHigher() === $this) {
                $user->setHigher(null);
            }
        }

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

    public function getBuyLimit(): ?float
    {
        return $this->buyLimit;
    }

    public function setBuyLimit(float $buyLimit): static
    {
        $this->buyLimit = $buyLimit;

        return $this;
    }

    public function getLvcHigher(): ?float
    {
        return $this->lvcHigher;
    }

    public function setLvcHigher(float $lvcHigher): static
    {
        $this->lvcHigher = $lvcHigher;

        return $this;
    }

    public function getLvcBuyLimit(): ?float
    {
        return $this->lvcBuyLimit;
    }

    public function setLvcBuyLimit(float $lvcBuyLimit): static
    {
        $this->lvcBuyLimit = $lvcBuyLimit;

        return $this;
    }

    public function getDailyCac(): ?Cac
    {
        return $this->dailyCac;
    }

    public function setDailyCac(?Cac $dailyCac): static
    {
        $this->dailyCac = $dailyCac;

        return $this;
    }

    public function getDailyLvc(): ?Lvc
    {
        return $this->dailyLvc;
    }

    public function setDailyLvc(?Lvc $dailyLvc): static
    {
        $this->dailyLvc = $dailyLvc;

        return $this;
    }

    /**
     * @return Collection<int, Position>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(Position $position): static
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
            $position->setBuyLimit($this);
        }

        return $this;
    }

    public function removePosition(Position $position): static
    {
        if ($this->positions->removeElement($position)) {
            // set the owning side to null (unless already changed)
            if ($position->getBuyLimit() === $this) {
                $position->setBuyLimit(null);
            }
        }

        return $this;
    }
}
