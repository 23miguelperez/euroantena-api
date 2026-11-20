# Euroantena: API (Symfony)

API REST que da servicio a la web de Euroantena (frontend en Astro, repositorio `euroantena`). Incluye:

- **Login** con JWT y **registro** de usuarios.
- **Calendario de citas** con el servicio técnico (2 técnicos: cada hora admite 2 citas).
- **Avisos por correo** a la empresa cuando alguien se registra o pide cita.

## Requisitos

- PHP 8.2 o superior, con las extensiones `sodium`, `openssl`, `pdo_sqlite`, `sqlite3`, `mbstring` e `intl`.
- Composer.
- Node.js 18 o superior (para el frontend).
- Opcional: [Mailpit](https://github.com/axllent/mailpit/releases) para ver los correos en local.

Comprobar las extensiones de PHP:

```powershell
php -m | findstr /i "sodium openssl sqlite"
```

Si falta alguna, hay que activarla en `php.ini` (quitar el `;` de la línea `extension=...`) y abrir una terminal nueva.

## 1. Poner en marcha la API

```powershell
git clone https://github.com/23miguelperez/euroantena-api.git
cd euroantena-api
composer install
```

**Configuración local.** Copiar el archivo de ejemplo:

```powershell
copy .env.local.example .env.local
```

**Claves JWT** (no se suben al repositorio, se generan en cada equipo):

```powershell
php bin/console lexik:jwt:generate-keypair
```

Si da el error `error:80000003:system library::No such process` (habitual en Windows), indicar dónde está la configuración de OpenSSL y repetir:

```powershell
$env:OPENSSL_CONF = "C:\php\extras\ssl\openssl.cnf"
php bin/console lexik:jwt:generate-keypair --overwrite
```

(La ruta depende de dónde esté instalado PHP; el archivo `openssl.cnf` suele estar en `extras\ssl` dentro de la carpeta de PHP.)

**Base de datos** (SQLite, se crea sola en `var/data.db`):

```powershell
php bin/console doctrine:migrations:migrate --no-interaction
```

**Arrancar el servidor:**

```powershell
php -S localhost:8000 -t public
```

Dejar esta terminal abierta. La API queda en `http://localhost:8000`.

## 2. Crear el usuario de prueba

Hay dos formas:

**A) Con un comando** (en otra terminal, dentro de la carpeta de la API):

```powershell
php bin/console app:create-user profesor@test.com Profesor1234
```

**B) Desde la web**, pulsando "Entrar" y luego "Regístrate" (la contraseña necesita al menos 8 caracteres).

## 3. Poner en marcha el frontend

```powershell
git clone https://github.com/23miguelperez/euroantena.git
cd euroantena
copy .env.example .env
npm install
npm run dev
```

La web queda en `http://localhost:4321`. El archivo `.env` debe contener:

```
PUBLIC_API_URL=http://localhost:8000
```

> La API tiene que estar arrancada (paso 1) mientras se prueba la web. La versión de la web publicada en Vercel no tiene API conectada, así que el login y el calendario solo funcionan en local.

## 4. Correos (opcional)

Los avisos a la empresa se envían con Symfony Mailer. Para verlos en local sin enviar correos reales:

1. Ejecutar `mailpit.exe` (recibe en el puerto 1025).
2. Abrir `http://localhost:8025` para ver la bandeja.

Si Mailpit no está en marcha, la aplicación funciona igual: el error de envío se registra en el log y la cita o el registro se guardan con normalidad.

Las direcciones se configuran en `.env.local`:

| Variable | Uso |
|---|---|
| `MAILER_DSN` | Servidor SMTP (en local, Mailpit) |
| `MAIL_FROM` | Remitente de los avisos |
| `MAIL_EMPRESA` | Dirección de la empresa que recibe los avisos |

## 5. Qué probar

1. **Registro y login:** crear una cuenta desde el popup e iniciar sesión. Con una contraseña incorrecta debe mostrar un error.
2. **Calendario:** en `/s-tecnico`, los días laborables muestran cuántas horas libres quedan. Sábados, domingos y días pasados salen en gris.
3. **Pedir cita:** sin sesión, al elegir una hora aparece el aviso para iniciar sesión. Con sesión, se rellena el formulario y se reserva.
4. **Capacidad:** cada hora admite 2 citas. Con dos cuentas distintas, la tercera reserva a la misma hora debe rechazarse y la hora aparece tachada.
5. **Mis citas:** la cita aparece en "Mis citas" y se puede cancelar; el hueco vuelve a quedar libre.
6. **Correo:** en Mailpit llegan los avisos "Nuevo usuario registrado" y "Nueva cita".

## Endpoints

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/api/register` | Público | Crea una cuenta |
| POST | `/api/login` | Público | Devuelve el token JWT |
| GET | `/api/me` | Con sesión | Datos del usuario actual |
| GET | `/api/citas/disponibilidad?fecha=AAAA-MM-DD` | Público | Horas libres de un día |
| GET | `/api/citas/disponibilidad-mes?mes=AAAA-MM` | Público | Horas libres por día de un mes |
| POST | `/api/citas` | Con sesión | Reserva una cita |
| GET | `/api/citas/mias` | Con sesión | Citas del usuario |
| DELETE | `/api/citas/{id}` | Con sesión | Cancela una cita propia |

Las rutas con sesión necesitan la cabecera `Authorization: Bearer <token>`.

## Notas de diseño

- Horario: lunes a viernes, citas de 1 hora a las 09, 10, 11, 12, 13, 16, 17 y 18 h (zona horaria Europe/Madrid). Se cambia en `src/Controller/CitaController.php`.
- La capacidad por hora (`CAPACIDAD = 2`) representa a los dos técnicos, que se organizan entre ellos.
- Las contraseñas se guardan con hash; el email se normaliza a minúsculas.
- Un usuario no puede ver ni cancelar citas de otro.
- El token se guarda en `localStorage` del navegador (válido para este proyecto; en producción se recomendaría una cookie `HttpOnly`).