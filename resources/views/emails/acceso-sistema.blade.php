<x-mail::message>
# Acceso al sistema de convalidaciones

Hola **{{ $usuario->nombre }}**, se creó tu cuenta en **USIL Convalidaciones**.

- **Usuario:** {{ $usuario->email }}
- **Contraseña temporal:** {{ $password }}

<x-mail::button :url="$url" color="primary">
Ingresar al sistema
</x-mail::button>

Por tu seguridad, deberás **cambiar la contraseña** la primera vez que ingreses.
Si no solicitaste este acceso, avisa al administrador del sistema.

Gracias,<br>
**USIL Convalidaciones**
</x-mail::message>
