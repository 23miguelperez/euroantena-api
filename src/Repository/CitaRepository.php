<?php

namespace App\Repository;

use App\Entity\Cita;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CitaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cita::class);
    }

    /** @return array<string,int> citas por hora ese día, ej. ['10:00' => 2] */
    public function citasPorHora(\DateTimeImmutable $dia): array
    {
        $desde = $dia->setTime(0, 0);
        $hasta = $desde->modify('+1 day');

        $citas = $this->createQueryBuilder('c')
            ->where('c.inicio >= :desde')
            ->andWhere('c.inicio < :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->getQuery()
            ->getResult();

        $conteo = [];
        foreach ($citas as $c) {
            $hora = $c->getInicio()->format('H:i');
            $conteo[$hora] = ($conteo[$hora] ?? 0) + 1;
        }

        return $conteo;
    }

    public function contarEn(\DateTimeImmutable $inicio): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.inicio = :i')
            ->setParameter('i', $inicio)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string,array<string,int>> ej. ['2026-11-23' => ['10:00' => 2]] */
    public function conteoPorRango(\DateTimeImmutable $desde, \DateTimeImmutable $hasta): array
    {
        $citas = $this->createQueryBuilder('c')
            ->where('c.inicio >= :desde')
            ->andWhere('c.inicio < :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->getQuery()
            ->getResult();

        $conteo = [];
        foreach ($citas as $c) {
            $fecha = $c->getInicio()->format('Y-m-d');
            $hora = $c->getInicio()->format('H:i');
            $conteo[$fecha][$hora] = ($conteo[$fecha][$hora] ?? 0) + 1;
        }

        return $conteo;
    }
}