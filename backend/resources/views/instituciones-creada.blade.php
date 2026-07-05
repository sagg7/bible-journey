<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bible Journey — Institución registrada</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; max-width: 560px; margin: 3rem auto; padding: 0 1.25rem 4rem; line-height: 1.6; color: #1a1a1a; }
        .card { border: 1px solid #e2ddd2; border-radius: 14px; padding: 2rem; background: #fff; }
        .credentials { background: #fff8e1; border: 1px solid #f0dca0; border-radius: 8px; padding: 1.2rem; margin: 1.5rem 0; }
        .credentials code { font-size: 1.1rem; font-weight: 700; }
        .btn { display: inline-block; padding: .85rem 1.6rem; border-radius: 8px; font-weight: 600; text-decoration: none; background: #2b2140; color: #fff; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>¡{{ $institution->name }} está registrada!</h1>

    @if ($generatedPassword)
        <p>Creamos una cuenta de administrador para <strong>{{ $adminUser->email }}</strong>. Guarda esta contraseña temporal — no la mostraremos de nuevo:</p>
        <div class="credentials">
            Correo: <code>{{ $adminUser->email }}</code><br>
            Contraseña: <code>{{ $generatedPassword }}</code>
        </div>
        <p>Con estos datos podrás entrar al panel de administración en <code>/admin</code> para gestionar a los miembros de tu institución, una vez completado el pago.</p>
    @else
        <p>Vinculamos tu cuenta existente (<strong>{{ $adminUser->email }}</strong>) como administradora de esta institución. Usa tu contraseña habitual para entrar a <code>/admin</code>.</p>
    @endif

    <p>Ahora falta completar el pago para activar la suscripción:</p>
    <a class="btn" href="{{ $checkoutUrl }}">Continuar al pago seguro (Stripe)</a>
</div>
</body>
</html>
