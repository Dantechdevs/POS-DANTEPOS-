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
    <meta name="description" content="DantePOS is a point-of-sale system for Kenyan shops: fast selling, M-Pesa, credit and debtors, stock control, reports and daily sales summaries.">
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --blue:#1d4ed8; --sky:#3b82f6; --green:#16a34a; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 24px; }

        .hero { background: linear-gradient(135deg, #4f46e5 0%, #2563eb 60%, #1d4ed8 100%); color: #fff; padding-bottom: 84px; }
        nav { display: flex; align-items: center; justify-content: space-between; padding: 22px 0; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 20px; }
        .brand img { height: 34px; width: auto; border-radius: 6px; background: #fff; padding: 2px; }
        .nav-links { display: flex; align-items: center; gap: 22px; }
        .nav-link { font-weight: 600; opacity: .95; font-size: 15px; }
        .nav-link:hover { opacity: 1; text-decoration: underline; }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 999px; font-weight: 700; font-size: 16px; border: 2px solid transparent; transition: transform .15s; }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: #fff; color: var(--blue); box-shadow: 0 8px 24px rgba(15,23,42,.25); }
        .btn-ghost { border-color: rgba(255,255,255,.7); color: #fff; }
        .btn-solid { background: var(--blue); color: #fff; }

        .hero-body { text-align: center; padding: 64px 0 8px; max-width: 780px; margin: 0 auto; }
        .pill { display: inline-block; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3); padding: 6px 14px; border-radius: 999px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        h1 { font-size: clamp(34px, 6vw, 56px); line-height: 1.1; font-weight: 800; letter-spacing: -.5px; }
        .lead { margin: 20px auto 32px; font-size: clamp(17px, 2.2vw, 20px); opacity: .95; max-width: 640px; }
        .cta { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

        .stats { margin-top: -44px; }
        .stats .grid { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; text-align: center; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        .stat b { display: block; font-size: 17px; }
        .stat span { color: var(--muted); font-size: 14px; }

        section.block { padding: 72px 0 0; }
        .kicker { color: var(--blue); font-weight: 700; font-size: 14px; letter-spacing: .6px; text-transform: uppercase; }
        h2 { font-size: clamp(26px, 4vw, 36px); line-height: 1.2; margin: 8px 0 12px; }
        .sub { color: var(--muted); max-width: 640px; font-size: 17px; margin-bottom: 32px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 26px; box-shadow: 0 10px 30px rgba(15,23,42,.05); }
        .icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: #eff6ff; font-size: 22px; margin-bottom: 14px; }
        .card h3 { font-size: 19px; margin-bottom: 10px; }
        .card ul { list-style: none; }
        .card li { position: relative; padding-left: 26px; margin: 8px 0; color: #334155; font-size: 15px; }
        .card li::before { content: "\2713"; position: absolute; left: 0; color: var(--green); font-weight: 800; }

        .steps { counter-reset: s; display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; }
        .step { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; position: relative; }
        .step::before { counter-increment: s; content: counter(s); display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: var(--blue); color: #fff; font-weight: 800; margin-bottom: 12px; }
        .step h3 { font-size: 18px; margin-bottom: 6px; }
        .step p { color: var(--muted); font-size: 15px; }

        .shops { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .shop { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px; text-align: center; }
        .shop .e { font-size: 34px; }
        .shop h3 { font-size: 17px; margin: 8px 0 4px; }
        .shop p { color: var(--muted); font-size: 14px; }

        .band { margin-top: 72px; background: #fff; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .band .wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 18px; padding: 34px 24px; }
        .band h2 { margin: 0; font-size: 26px; }
        .band p { color: var(--muted); }

        footer { padding: 30px 0 40px; text-align: center; color: var(--muted); font-size: 14px; }
        footer a { color: var(--blue); font-weight: 600; }

        @media (max-width: 640px) {
            .nav-links .nav-link { display: none; }
            .hero-body { padding-top: 44px; }
            .cta .btn { width: 100%; text-align: center; }
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
                    <a class="nav-link" href="#what">What it does</a>
                    <a class="nav-link" href="#how">How a sale works</a>
                    <a class="nav-link" href="#shops">Who it's for</a>
                    <a class="btn btn-ghost" href="{{ $loginUrl }}">Sign in</a>
                </div>
            </nav>

            <div class="hero-body">
                <span class="pill">Built for Kenyan retail</span>
                <h1>The till that runs your whole shop.</h1>
                <p class="lead">Sell fast, take cash, M-Pesa and credit, keep stock under control, and see exactly what you earn. All from one screen.</p>
                <div class="cta">
                    <a class="btn btn-primary" href="{{ $loginUrl }}">Sign in to the till</a>
                    <a class="btn btn-ghost" href="#what">See what it does</a>
                </div>
            </div>
        </div>
    </header>

    <div class="wrap stats">
        <div class="grid">
            <div class="stat"><b>Cash &middot; M-Pesa &middot; Card</b><span>Every payment in one sale</span></div>
            <div class="stat"><b>Credit &amp; debtors</b><span>Know who owes you</span></div>
            <div class="stat"><b>Live stock</b><span>Counts drop as you sell</span></div>
            <div class="stat"><b>Daily summary</b><span>Sales sent to your phone</span></div>
        </div>
    </div>

    <section class="block wrap" id="what">
        <span class="kicker">What DantePOS does</span>
        <h2>Everything a shop does in a day, in one place</h2>
        <p class="sub">From the first sale of the morning to the figures you check at night.</p>

        <div class="grid">
            <div class="card"><div class="icon">&#128722;</div><h3>Sell quickly</h3>
                <ul>
                    <li>Scan a barcode or search by name or SKU</li>
                    <li>Discounts, VAT and several payments on one sale</li>
                    <li>Hold a sale and come back to it</li>
                    <li>Print the receipt or share it, with the customer's reward points on it</li>
                </ul></div>

            <div class="card"><div class="icon">&#128179;</div><h3>Get paid, every way</h3>
                <ul>
                    <li>Cash, card, bank transfer and cheque</li>
                    <li>M-Pesa: send the STK Push and see it confirmed</li>
                    <li>Gift cards you can issue, top up and redeem</li>
                    <li>Credit sales with a limit for each customer</li>
                </ul></div>

            <div class="card"><div class="icon">&#128230;</div><h3>Control your stock</h3>
                <ul>
                    <li>Products with variations, units and categories</li>
                    <li>Purchases from suppliers, with what you owe them</li>
                    <li>Stocktake: count the shelves and fix the difference</li>
                    <li>Reorder list by supplier, and expiry dates to watch</li>
                </ul></div>

            <div class="card"><div class="icon">&#128101;</div><h3>Customers and credit</h3>
                <ul>
                    <li>Customer accounts with a full ledger</li>
                    <li>Debtor aging: 0-30, 31-60, 61-90 and 90+ days</li>
                    <li>WhatsApp statements and SMS reminders for overdue balances</li>
                    <li>Reward points that customers collect on each visit</li>
                </ul></div>

            <div class="card"><div class="icon">&#128200;</div><h3>Know your numbers</h3>
                <ul>
                    <li>Profit and margin for every product</li>
                    <li>An alert when something sells below cost</li>
                    <li>M-Pesa reconciliation: every shilling accounted for</li>
                    <li>A daily sales summary by SMS at closing time</li>
                </ul></div>

            <div class="card"><div class="icon">&#128274;</div><h3>Stay in control</h3>
                <ul>
                    <li>Separate roles for cashiers and managers</li>
                    <li>Manager overrides that are recorded</li>
                    <li>Automatic nightly database backups</li>
                    <li>More than one shop location from one account</li>
                </ul></div>
        </div>
    </section>

    <section class="block wrap" id="how">
        <span class="kicker">How a sale works</span>
        <h2>From the shelf to the receipt in four steps</h2>
        <p class="sub">The same flow whether you sell ten items a day or a few hundred.</p>
        <div class="steps">
            <div class="step"><h3>Add the items</h3><p>Scan the barcode or search. Quantities, prices and VAT fill in by themselves.</p></div>
            <div class="step"><h3>Pick the customer</h3><p>Use Walk-In for cash sales. Choose a named customer for credit, gift cards and reward points.</p></div>
            <div class="step"><h3>Take payment</h3><p>Cash, M-Pesa, card, gift card, or put it on the customer's account within their limit.</p></div>
            <div class="step"><h3>Print the receipt</h3><p>Stock updates, the sale lands in your reports, and you get a daily summary at closing time.</p></div>
        </div>
    </section>

    <section class="block wrap" id="shops">
        <span class="kicker">Who it's for</span>
        <h2>Built for your kind of shop</h2>
        <p class="sub">One system, set up for how your business sells.</p>
        <div class="grid shops">
            <div class="shop"><div class="e">&#128717;</div><h3>Supermarkets &amp; general retail</h3><p>Fast barcode selling and thousands of products.</p></div>
            <div class="shop"><div class="e">&#128138;</div><h3>Pharmacies</h3><p>Expiry dates on every batch, with a watch list.</p></div>
            <div class="shop"><div class="e">&#128241;</div><h3>Electronics &amp; hardware</h3><p>Higher-value items, margins and supplier credit.</p></div>
            <div class="shop"><div class="e">&#128736;</div><h3>Service businesses</h3><p>Sell services next to products, with customer accounts.</p></div>
            <div class="shop"><div class="e">&#127869;</div><h3>Cafés &amp; restaurants</h3><p>Quick counter sales and M-Pesa at the table.</p></div>
        </div>
    </section>

    <section class="band">
        <div class="wrap">
            <div>
                <h2>Ready to start selling?</h2>
                <p>Sign in with your username and password.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <a class="btn btn-solid" href="{{ $loginUrl }}">Sign in</a>
                @if ($canRegister)<a class="btn" style="border-color:var(--blue);color:var(--blue)" href="{{ $registerUrl }}">Register your business</a>@endif
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap">&copy; {{ date('Y') }} DantePOS &middot; Built by <a href="https://dantechdevelopers.com/" target="_blank" rel="noopener">Dantech Developers</a></div>
    </footer>
</body>
</html>
