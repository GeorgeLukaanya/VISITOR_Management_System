<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Visitor Management')</title>
    <style>
        :root { --ink:#1f2933; --muted:#7b8794; --line:#e4e7eb; --brand:#2453ff; --bg:#f5f7fa; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
               color: var(--ink); background: var(--bg); }
        header { background:#fff; border-bottom:1px solid var(--line); padding:14px 24px;
                 display:flex; align-items:center; justify-content:space-between; }
        header .brand { font-weight:700; }
        header .who { color:var(--muted); font-size:14px; }
        main { max-width: 1000px; margin: 28px auto; padding: 0 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: var(--muted); margin: 0 0 20px; font-size: 14px; }
        table { width:100%; border-collapse: collapse; background:#fff; border:1px solid var(--line);
                border-radius:8px; overflow:hidden; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); font-size:14px; }
        th { background:#fbfcfd; color:var(--muted); font-weight:600; }
        tr:last-child td { border-bottom:none; }
        .btn { display:inline-block; background:var(--brand); color:#fff; text-decoration:none;
               padding:8px 14px; border-radius:6px; font-size:14px; border:none; cursor:pointer; }
        .btn.secondary { background:#fff; color:var(--ink); border:1px solid var(--line); }
        .row { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:24px; max-width:380px; margin:80px auto; }
        .field { margin-bottom:14px; }
        label { display:block; font-size:13px; color:var(--muted); margin-bottom:4px; }
        input { width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:6px; font-size:14px; }
        .error { color:#c0392b; font-size:13px; margin-top:6px; }
        .empty { color:var(--muted); padding:24px; text-align:center; }
        .pill { font-size:12px; padding:2px 8px; border-radius:999px; background:#eef2ff; color:var(--brand); }
    </style>
</head>
<body>
    @auth
        <header>
            <div class="brand">Visitor Management</div>
            <div style="display:flex; align-items:center; gap:14px;">
                <span class="who">{{ auth()->user()->name }} &middot; {{ str_replace('_',' ', auth()->user()->role->value) }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn secondary" type="submit">Log out</button>
                </form>
            </div>
        </header>
    @endauth
    <main>
        @yield('content')
    </main>
</body>
</html>
