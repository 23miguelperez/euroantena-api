<?php

namespace App\Entity;

use App\Repository\CitaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CitaRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CITA_USUARIO_INICIO', fields: ['usuario', 'inicio'])]
class Cita
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $usuario = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $inicio = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $fin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notas = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creadaEn = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telefono = null;

    public function __construct()
    {
        $this->creadaEn = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
    }

    public function getId(): ?int { return $this->id; }

    public function getUsuario(): ?User { return $this->usuario; }
    public function setUsuario(User $usuario): static { $this->usuario = $usuario; return $this; }

    public function getInicio(): ?\DateTimeImmutable { return $this->inicio; }
    public function setInicio(\DateTimeImmutable $inicio): static { $this->inicio = $inicio; return $this; }

    public function getFin(): ?\DateTimeImmutable { return $this->fin; }
    public function setFin(\DateTimeImmutable $fin): static { $this->fin = $fin; return $this; }

    public function getNotas(): ?string { return $this->notas; }
    public function setNotas(?string $notas): static { $this->notas = $notas; return $this; }

    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(?string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $telefono): static { $this->telefono = $telefono; return $this; }
}