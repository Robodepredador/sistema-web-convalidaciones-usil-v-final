<x-mail::message>
# Acceso a tu portal de seguimiento

Hola **{{ $postulante->nombres }}**, registramos tu solicitud de convalidación en **USIL Convalidaciones**.

Con estas credenciales puedes ingresar al portal y seguir el estado de tu trámite:

- **Usuario:** {{ $postulante->email }}
- **Contraseña temporal:** {{ $password }}

<x-mail::button :url="$url" color="primary">
Ingresar al portal
</x-mail::button>

Por tu seguridad, deberás **cambiar la contraseña** la primera vez que ingreses.

Gracias,<br>
**USIL Convalidaciones**
</x-mail::message>
