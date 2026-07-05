<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bible Journey — Lee la Biblia en el orden en que ocurrió</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, system-ui, "Segoe UI", sans-serif;
            margin: 0; padding: 0; color: #1a1a1a; line-height: 1.6;
            background: #fbfaf7;
        }
        a { color: inherit; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 0 1.25rem; }
        header.hero {
            background: linear-gradient(180deg, #2b2140 0%, #3d2f5c 100%);
            color: #fff; padding: 4rem 0 5rem;
        }
        header.hero h1 { font-size: 2.2rem; margin: 0 0 1rem; max-width: 720px; }
        header.hero p.lead { font-size: 1.15rem; opacity: .9; max-width: 620px; }
        .cta-row { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: .85rem 1.6rem; border-radius: 8px;
            font-weight: 600; text-decoration: none; font-size: 1rem;
            border: none; cursor: pointer;
        }
        .btn-primary { background: #f4b942; color: #2b2140; }
        .btn-outline { background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,.5); }
        section { padding: 3.5rem 0; }
        section h2 { font-size: 1.6rem; margin-bottom: .5rem; }
        section p.section-sub { color: #555; max-width: 640px; margin-top: 0; }
        .free-banner {
            background: #eef7ee; border: 1px solid #cfe8cf; border-radius: 12px;
            padding: 1.5rem; margin-top: 2rem;
        }
        .free-banner strong { color: #256d25; }
        .plans { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem; }
        @media (max-width: 720px) { .plans { grid-template-columns: 1fr; } }
        .plan-card {
            border: 1px solid #e2ddd2; border-radius: 14px; padding: 1.75rem;
            background: #fff;
        }
        .plan-card h3 { margin-top: 0; }
        .price { font-size: 2rem; font-weight: 700; margin: .5rem 0; }
        .price small { font-size: 1rem; font-weight: 400; color: #666; }
        form.institucional { margin-top: 1.5rem; display: grid; gap: .9rem; }
        form.institucional label { font-size: .9rem; font-weight: 600; display: block; margin-bottom: .3rem; }
        form.institucional input, form.institucional select {
            width: 100%; padding: .6rem .7rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;
        }
        .radio-row { display: flex; gap: 1.5rem; }
        .radio-row label { font-weight: 500; display: flex; align-items: center; gap: .4rem; }
        .radio-row input { width: auto; }
        .errors { background: #fdeaea; border: 1px solid #f3b9b9; color: #922; padding: .9rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        footer { padding: 2.5rem 0; text-align: center; color: #888; font-size: .9rem; }
        footer a { color: #555; }
    </style>
</head>
<body>

<header class="hero">
    <div class="wrap">
        <h1>Lee la Biblia en el orden en que realmente ocurrió</h1>
        <p class="lead">Bible Journey reorganiza toda la Escritura — Antiguo y Nuevo Testamento — en una sola línea cronológica, con contexto histórico, modo de estudio y Ezra, tu asistente de investigación bíblica.</p>
        <div class="cta-row">
            <a class="btn btn-primary" href="https://play.google.com/store/apps/details?id=com.codeshore.biblejourney">Descargar en Google Play</a>
            <a class="btn btn-outline" href="#instituciones">Soy una institución</a>
        </div>
    </div>
</header>

<section class="wrap">
    <div class="free-banner">
        <strong>Empieza gratis, sin tarjeta.</strong> Todo "Los patriarcas" y "David y Salomón" — lectura cronológica completa, modo de estudio, Ezra y Espíritu de Profecía — es 100% gratis para que vivas la experiencia completa antes de decidir.
    </div>
</section>

<section class="wrap">
    <h2>Planes</h2>
    <p class="section-sub">Elige el plan individual desde la app, o registra a tu institución (iglesia, escuela, grupo de estudio) aquí mismo.</p>
    <div class="plans">
        <div class="plan-card">
            <h3>Individual</h3>
            <div class="price">$6.99 <small>/ mes</small></div>
            <p>Acceso completo a los 540 eventos del plan cronológico, Ezra ilimitado y Espíritu de Profecía. Se compra dentro de la app (Google Play / App Store).</p>
            <a class="btn btn-primary" href="https://play.google.com/store/apps/details?id=com.codeshore.biblejourney">Descargar la app</a>
        </div>
        <div class="plan-card" id="instituciones">
            <h3>Institucional</h3>
            <div class="price">$4.95 <small>/ asiento / mes</small></div>
            <p>O $49.50/asiento/año (2 meses gratis). Mínimo 10 asientos. Administra a tus miembros desde un panel dedicado.</p>

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form class="institucional" method="POST" action="{{ route('instituciones.store') }}">
                @csrf
                <div>
                    <label for="name">Nombre de la institución</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label for="seats">Número de asientos (mínimo 10)</label>
                    <input type="number" id="seats" name="seats" min="10" value="{{ old('seats', 10) }}" required>
                </div>
                <div>
                    <label>Periodicidad</label>
                    <div class="radio-row">
                        <label><input type="radio" name="billing_period" value="monthly" checked> Mensual</label>
                        <label><input type="radio" name="billing_period" value="annual"> Anual (2 meses gratis)</label>
                    </div>
                </div>
                <div>
                    <label for="admin_name">Tu nombre</label>
                    <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required>
                </div>
                <div>
                    <label for="admin_email">Tu correo (será el administrador)</label>
                    <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required>
                </div>
                <button type="submit" class="btn btn-primary">Registrar institución y continuar al pago</button>
            </form>
        </div>
    </div>
</section>

<footer>
    <div class="wrap">
        <p><a href="/privacy">Política de privacidad</a></p>
        <p>&copy; {{ date('Y') }} Bible Journey · Codeshore</p>
    </div>
</footer>

</body>
</html>
