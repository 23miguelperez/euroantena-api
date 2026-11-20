<?php

namespace App\Controller;

use App\Entity\Cita;
use App\Entity\User;
use App\Repository\CitaRepository;
use App\Service\EmpresaNotifier;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/citas')]
class CitaController extends AbstractController
{
    private const TZ = 'Europe/Madrid';
    private const SLOTS = ['09:00', '10:00', '11:00', '12:00', '13:00', '16:00', '17:00', '18:00'];
    private const DURACION_MIN = 60;
    private const CAPACIDAD = 2; // citas simultáneas (nº de técnicos)

    public function __construct(
        private CitaRepository $citas,
        private EntityManagerInterface $em,
        private EmpresaNotifier $notifier,
    ) {
    }

    private function tz(): \DateTimeZone
    {
        return new \DateTimeZone(self::TZ);
    }

    private function esLaborable(\DateTimeImmutable $d): bool
    {
        return (int) $d->format('N') <= 5; // lunes a viernes
    }

    private function slotsDelDia(\DateTimeImmutable $dia, array $conteoDia, \DateTimeImmutable $ahora): array
    {
        $slots = [];
        foreach (self::SLOTS as $hora) {
            [$h, $m] = explode(':', $hora);
            $inicio = $dia->setTime((int) $h, (int) $m);
            $slots[] = [
                'hora' => $hora,
                'libre' => $inicio > $ahora && ($conteoDia[$hora] ?? 0) < self::CAPACIDAD,
            ];
        }

        return $slots;
    }

    private function citaToArray(Cita $c): array
    {
        return [
            'id' => $c->getId(),
            'inicio' => $c->getInicio()->format('Y-m-d\TH:i'),
            'fin' => $c->getFin()->format('Y-m-d\TH:i'),
            'notas' => $c->getNotas(),
        ];
    }

    #[Route('/disponibilidad', name: 'api_citas_disponibilidad', methods: ['GET'])]
    public function disponibilidad(Request $request): JsonResponse
    {
        $fecha = (string) $request->query->get('fecha');
        $dia = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $this->tz());
        if (!$dia || $dia->format('Y-m-d') !== $fecha) {
            return $this->json(['error' => 'Fecha no válida (usa AAAA-MM-DD)'], 422);
        }

        if (!$this->esLaborable($dia)) {
            return $this->json(['fecha' => $fecha, 'slots' => []]);
        }

        $ahora = new \DateTimeImmutable('now', $this->tz());
        $slots = $this->slotsDelDia($dia, $this->citas->citasPorHora($dia), $ahora);

        return $this->json(['fecha' => $fecha, 'slots' => $slots]);
    }

    #[Route('/disponibilidad-mes', name: 'api_citas_disponibilidad_mes', methods: ['GET'])]
    public function disponibilidadMes(Request $request): JsonResponse
    {
        $mes = (string) $request->query->get('mes');
        $primero = \DateTimeImmutable::createFromFormat('!Y-m', $mes, $this->tz());
        if (!$primero || $primero->format('Y-m') !== $mes) {
            return $this->json(['error' => 'Mes no válido (usa AAAA-MM)'], 422);
        }

        $siguiente = $primero->modify('first day of next month');
        $conteo = $this->citas->conteoPorRango($primero, $siguiente);
        $ahora = new \DateTimeImmutable('now', $this->tz());

        $dias = [];
        for ($dia = $primero; $dia < $siguiente; $dia = $dia->modify('+1 day')) {
            $fecha = $dia->format('Y-m-d');
            if (!$this->esLaborable($dia)) {
                $dias[$fecha] = 0;
                continue;
            }
            $slots = $this->slotsDelDia($dia, $conteo[$fecha] ?? [], $ahora);
            $dias[$fecha] = count(array_filter($slots, fn (array $s) => $s['libre']));
        }

        return $this->json(['mes' => $mes, 'dias' => $dias]);
    }

    #[Route('', name: 'api_citas_crear', methods: ['POST'])]
    public function crear(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Datos no válidos'], 400);
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            return $this->json(['error' => 'El nombre es obligatorio'], 422);
        }

        $telefono = trim((string) ($data['telefono'] ?? ''));
        if (!preg_match('/^[0-9+\s().-]{6,20}$/', $telefono)) {
            return $this->json(['error' => 'El teléfono no es válido'], 422);
        }

        $inicio = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) ($data['inicio'] ?? ''), $this->tz());
        if (!$inicio || !in_array($inicio->format('H:i'), self::SLOTS, true)) {
            return $this->json(['error' => 'Hora no válida'], 422);
        }

        if (!$this->esLaborable($inicio)) {
            return $this->json(['error' => 'Solo se puede pedir cita de lunes a viernes'], 422);
        }

        if ($inicio <= new \DateTimeImmutable('now', $this->tz())) {
            return $this->json(['error' => 'Esa hora ya ha pasado'], 422);
        }

        if ($this->citas->findOneBy(['usuario' => $user, 'inicio' => $inicio])) {
            return $this->json(['error' => 'Ya tienes otra cita a esa hora'], 409);
        }

        if ($this->citas->contarEn($inicio) >= self::CAPACIDAD) {
            return $this->json(['error' => 'Ese hueco ya está completo'], 409);
        }

        $notas = trim((string) ($data['notas'] ?? ''));

        $cita = (new Cita())
            ->setUsuario($user)
            ->setNombre($nombre)
            ->setTelefono($telefono)
            ->setInicio($inicio)
            ->setFin($inicio->modify('+' . self::DURACION_MIN . ' minutes'))
            ->setNotas($notas !== '' ? mb_substr($notas, 0, 1000) : null);

        try {
            $this->em->persist($cita);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'Ya tienes otra cita a esa hora'], 409);
        }

        // Aviso a la empresa (si falla, se registra en el log y la cita se mantiene)
        $this->notifier->citaNueva($cita);

        return $this->json($this->citaToArray($cita), 201);
    }

    #[Route('/mias', name: 'api_citas_mias', methods: ['GET'])]
    public function mias(#[CurrentUser] User $user): JsonResponse
    {
        $citas = $this->citas->findBy(['usuario' => $user], ['inicio' => 'ASC']);

        return $this->json(array_map(fn (Cita $c) => $this->citaToArray($c), $citas));
    }

    #[Route('/{id}', name: 'api_citas_cancelar', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function cancelar(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cita = $this->citas->find($id);

        if (!$cita || $cita->getUsuario()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Cita no encontrada'], 404);
        }

        // Se compara como texto para evitar desfases de zona horaria al leer de la base de datos
        $ahora = new \DateTimeImmutable('now', $this->tz());
        if ($cita->getInicio()->format('Y-m-d H:i') <= $ahora->format('Y-m-d H:i')) {
            return $this->json(['error' => 'No se puede cancelar una cita pasada'], 422);
        }

        $this->em->remove($cita);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }
}