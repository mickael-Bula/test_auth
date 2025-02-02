<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class Position
{
    /**
     * Déclaration d'une constante pour gérer la valeur d'une ligne.
     *
     * @var int
     */
    public const LINE_VALUE = 750;

    /**
     * Déclaration du pourcentage de baisse fixant le niveau de Buy_limit.
     *
     * @var float
     */
    public const SPREAD = 0.06; // on fixe ici la limite à 6 % de baisse, le palier d'achat étant fixé à 2 % pout 3 lignes

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['position_read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['position_read'])]
    private ?float $buyTarget = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['position_read'])]
    private ?float $sellTarget = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['position_read'])]
    private ?\DateTimeInterface $buyDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sellDate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isClosed = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isWaiting = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isRunning = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[Groups(['position_read'])]
    private ?LastHigh $buyLimit = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['position_read'])]
    private ?float $lvcBuyTarget = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['position_read'])]
    private ?float $lvcSellTarget = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['position_read'])]
    private ?int $quantity = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    private ?User $userPosition = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuyTarget(): ?float
    {
        return $this->buyTarget;
    }

    public function setBuyTarget(float $buyTarget): static
    {
        $this->buyTarget = $buyTarget;

        return $this;
    }

    public function getSellTarget(): ?float
    {
        return $this->sellTarget;
    }

    public function setSellTarget(float $sellTarget): static
    {
        $this->sellTarget = $sellTarget;

        return $this;
    }

    /**
     * Méthode appelée avant chaque Event persist et update de l'entité Position pour fixer la cible de revente à +10%.
     */
    #[ORM\PrePersist]
    #[ORM\PostPersist]
    public function SellTargetEvent(): void
    {
        if ($this->buyTarget) {
            $this->setSellTarget($this->buyTarget * 1.1);
        }
    }

    public function getBuyDate(): ?\DateTimeInterface
    {
        return $this->buyDate;
    }

    public function setBuyDate(\DateTimeInterface $buyDate): static
    {
        $this->buyDate = $buyDate;

        return $this;
    }

    public function getSellDate(): ?\DateTimeInterface
    {
        return $this->sellDate;
    }

    public function setSellDate(\DateTimeInterface $sellDate): static
    {
        $this->sellDate = $sellDate;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isClosed(): ?bool
    {
        return $this->isClosed;
    }

    public function setClosed(bool $isClosed): static
    {
        $this->isClosed = $isClosed;

        return $this;
    }

    public function isWaiting(): ?bool
    {
        return $this->isWaiting;
    }

    public function setWaiting(bool $isWaiting): static
    {
        $this->isWaiting = $isWaiting;

        return $this;
    }

    public function isRunning(): ?bool
    {
        return $this->isRunning;
    }

    public function setRunning(bool $isRunning): static
    {
        $this->isRunning = $isRunning;

        return $this;
    }

    public function getBuyLimit(): ?LastHigh
    {
        return $this->buyLimit;
    }

    public function setBuyLimit(?LastHigh $buyLimit): static
    {
        $this->buyLimit = $buyLimit;

        return $this;
    }

    public function getLvcBuyTarget(): ?float
    {
        return $this->lvcBuyTarget;
    }

    public function setLvcBuyTarget(float $lvcBuyTarget): static
    {
        $this->lvcBuyTarget = $lvcBuyTarget;

        return $this;
    }

    public function getLvcSellTarget(): ?float
    {
        return $this->lvcSellTarget;
    }

    public function setLvcSellTarget(float $lvcSellTarget): static
    {
        $this->lvcSellTarget = $lvcSellTarget;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUserPosition(): ?User
    {
        return $this->userPosition;
    }

    public function setUserPosition(?User $userPosition): static
    {
        $this->userPosition = $userPosition;

        return $this;
    }
}
