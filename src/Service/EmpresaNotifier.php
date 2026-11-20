<?php

namespace App\Service;

use App\Entity\Cita;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmpresaNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(MAIL_FROM)%')] private string $from,
        #[Autowire('%env(MAIL_EMPRESA)%')] private string $empresa,
    ) {
    }

    public function citaNueva(Cita $cita): void
    {
        $usuario = $cita->getUsuario();

        $cuerpo = implode("\n", [
            'Nueva cita solicitada desde la web.',
            '',
            'Fecha: ' . $cita->getInicio()->format('d/m/Y'),
            'Hora: ' . $cita->getInicio()->format('H:i'),
            '',
            'Nombre: ' . $cita->getNombre(),
            'Teléfono: ' . $cita->getTelefono(),
            'Email de la cuenta: ' . $usuario->getEmail(),
            '',
            'Qué necesita:',
            $cita->getNotas() ?: '(sin detalles)',
        ]);

        $email = (new Email())
            ->subject('Nueva cita: ' . $cita->getInicio()->format('d/m/Y H:i'))
            ->replyTo(new Address((string) $usuario->getEmail()))
            ->text($cuerpo);

        $this->enviar($email);
    }

    public function usuarioNuevo(User $user): void
    {
        $email = (new Email())
            ->subject('Nuevo usuario registrado')
            ->text("Se ha registrado una cuenta nueva en la web.\n\nEmail: " . $user->getEmail());

        $this->enviar($email);
    }

    private function enviar(Email $email): void
    {
        try {
            $email->from($this->from)->to($this->empresa);
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Un fallo de correo no debe romper la reserva ni el registro
            $this->logger->error('No se pudo enviar el correo a la empresa: ' . $e->getMessage());
        }
    }
}