@php
    $loginUrl = \Illuminate\Support\Facades\Route::has('login') ? route('login') : url('/login');
    $registerUrl = \Illuminate\Support\Facades\Route::has('business.getRegister') ? route('business.getRegister') : url('/business/register');
    $canRegister = config('constants.allow_registration', true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DantePOS | Point of sale for Kenyan retail</title>
    <meta name="description" content="DantePOS is a point-of-sale system built for Kenyan shops: M-Pesa payments, credit sales, stock control and daily reports.">
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --blue:#1d4ed8; --sky:#3b82f6; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 24px; }

        .hero { background: linear-gradient(135deg, #4f46e5 0%, #2563eb 60%, #1d4ed8 100%); color: #fff; padding-bottom: 96px; }
        nav { display: flex; align-items: center; justify-content: space-between; padding: 22px 0; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 20px; letter-spacing: .2px; }
        .brand img { height: 34px; width: auto; border-radius: 6px; background: #fff; padding: 2px; }
        .nav-links { display: flex; align-items: center; gap: 14px; }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 999px; font-weight: 700; font-size: 16px; border: 2px solid transparent; transition: transform .15s, box-shadow .15s, background .15s; }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: #fff; color: var(--blue); box-shadow: 0 8px 24px rgba(15,23,42,.25); }
        .btn-ghost { border-color: rgba(255,255,255,.7); color: #fff; }
        .btn-ghost:hover { background: rgba(255,255,255,.12); }
        .nav-link { font-weight: 600; opacity: .95; }

        .hero-body { text-align: center; padding: 72px 0 8px; max-width: 760px; margin: 0 auto; }
        .pill { display: inline-block; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3); padding: 6px 14px; border-radius: 999px; font-size: 14px; font-weight: 600; margin-bottom: 22px; }
        h1 { font-size: clamp(34px, 6vw, 56px); line-height: 1.1; font-weight: 800; letter-spacing: -.5px; }
        .lead { margin: 22px auto 34px; font-size: clamp(17px, 2.2vw, 20px); opacity: .95; max-width: 620px; }
        .cta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
        .trust { margin-top: 26px; font-size: 14px; opacity: .85; }

        .features { padding: 72px 0 24px; margin-top: -56px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 20px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 26px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
        .icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: #eff6ff; color: var(--blue); font-size: 22px; margin-bottom: 16px; }
        .card h3 { font-size: 18px; margin-bottom: 6px; }
        .card p { color: var(--muted); font-size: 15px; }

        .band { margin: 56px 0 0; background: #fff; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .band .wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 18px; padding: 34px 24px; }
        .band h2 { font-size: 24px; }
        .band p { color: var(--muted); }
        .btn-solid { background: var(--blue); color: #fff; }

        footer { padding: 30px 0 40px; text-align: center; color: var(--muted); font-size: 14px; }
        footer a { color: var(--blue); font-weight: 600; }

        @media (max-width: 560px) {
            .nav-links .nav-link { display: none; }
            .hero-body { padding-top: 48px; }
            .btn { width: 100%; text-align: center; }
            .cta { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="hero">
        <div class="wrap">
            <nav>
                <a class="brand" href="{{ url('/') }}">
                    <img src="{{ asset('img/logo-small.png') }}" alt="" onerror="this.style.display='none'">
                    <span>DantePOS</span>
                </a>
                <div class="nav-links">
                    @if ($canRegister)<a class="nav-link" href="{{ $registerUrl }}">Register</a>@endif
                    <a class="btn btn-ghost" href="{{ $loginUrl }}">Sign in</a>
                </div>
            </nav>

            <div class="hero-body">
                <span class="pill">Built for Kenyan retail</span>
                <h1>Run your shop from one simple till.</h1>
                <p class="lead">Take cash, M-Pesa and credit sales, keep stock under control and see what you really earn, every day.</p>
                <div class="cta">
                    <a class="btn btn-primary" href="{{ $loginUrl }}">Sign in to the till</a>
                    @if ($canRegister)<a class="btn btn-ghost" href="{{ $registerUrl }}">Register your business</a>@endif
                </div>
                <p class="trust">Prices in KSh &middot; M-Pesa ready &middot; Works on a shop PC or tablet</p>
            </div>
        </div>
    </header>

    <main class="wrap features">
        <div class="grid">
            <div class="card"><div class="icon">&#128179;</div><h3>M-Pesa at the till</h3><p>Send an STK Push, see the payment confirmed, and match every shilling in the reconciliation report.</p></div>
            <div class="card"><div class="icon">&#128210;</div><h3>Credit and debtors</h3><p>Credit limits per customer, debtor aging, WhatsApp statements and SMS reminders for overdue balances.</p></div>
            <div class="card"><div class="icon">&#127873;</div><h3>Gift cards and points</h3><p>Issue, top up and redeem gift cards. Reward points are printed on the customer's receipt.</p></div>
            <div class="card"><div class="icon">&#128230;</div><h3>Stock under control</h3><p>Count shelves with a stocktake, watch expiry dates and get a reorder list grouped by supplier.</p></div>
            <div class="card"><div class="icon">&#128200;</div><h3>Know your profit</h3><p>Margins per product, a guard against selling below cost, and a daily sales summary sent to your phone.</p></div>
            <div class="card"><div class="icon">&#128274;</div><h3>Safe by design</h3><p>Roles for cashiers and managers, manager overrides that are logged, and automatic nightly backups.</p></div>
        </div>
    </main>

    <section class="band">
        <div class="wrap">
            <div>
                <h2>Ready to start selling?</h2>
                <p>Sign in with your username and password.</p>
            </div>
            <a class="btn btn-solid" href="{{ $loginUrl }}">Sign in</a>
        </div>
    </section>

    <footer>
        <div class="wrap">&copy; {{ date('Y') }} DantePOS &middot; Built by <a href="https://dantechdevelopers.com/" target="_blank" rel="noopener">Dantech Developers</a></div>
    </footer>
</body>
</html>
