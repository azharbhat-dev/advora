<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/logo.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advora — AI-Powered Lead & Call Generation</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
/* ── Homepage-only additions ── */
.hp-nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:0 32px;height:64px;
    background:rgba(5,5,12,0.96);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
    border-bottom:1px solid var(--border);position:sticky;top:0;z-index:200;
}
.hp-nav-links{display:flex;gap:28px}
.hp-nav-links a{color:var(--text-2);text-decoration:none;font-size:13.5px;font-weight:500;transition:color .15s}
.hp-nav-links a:hover{color:var(--text)}

/* Hero */
.hp-hero{
    position:relative;text-align:center;
    padding:96px 24px 0;overflow:hidden;
    background:radial-gradient(ellipse 820px 480px at 50% -40px,rgba(255,200,0,0.07),transparent);
}
.hp-hero-badge{
    display:inline-flex;align-items:center;gap:8px;
    background:var(--yellow-dim);border:1px solid rgba(255,200,0,.2);
    padding:6px 16px;border-radius:20px;font-size:12px;color:var(--yellow);font-weight:600;margin-bottom:26px;
}
.hp-hero h1{
    font-size:52px;font-weight:800;letter-spacing:-2px;line-height:1.08;
    max-width:740px;margin:0 auto 20px;
}
.hp-hero h1 span{color:var(--yellow)}
.hp-hero-sub{
    font-size:16px;color:var(--text-2);max-width:500px;
    margin:0 auto 38px;line-height:1.7;
}
.hp-hero-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:60px}
.btn-xl{padding:14px 32px;border-radius:var(--r);font-size:15px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:9px}
.btn-xl-primary{background:var(--yellow);color:#000;box-shadow:0 0 32px rgba(255,200,0,.18)}
.btn-xl-primary:hover{background:var(--yellow-2);transform:translateY(-2px);box-shadow:0 0 52px rgba(255,200,0,.32)}
.btn-xl-ghost{background:transparent;color:var(--text);border:1px solid var(--border-2)}
.btn-xl-ghost:hover{border-color:var(--border-hi);color:var(--yellow)}

/* Hero image */
.hp-hero-img{
    max-width:940px;margin:0 auto;position:relative;
    border-radius:16px 16px 0 0;overflow:hidden;
    border:1px solid var(--border-2);border-bottom:none;
    box-shadow:0 -8px 60px rgba(255,200,0,0.06),0 40px 100px rgba(0,0,0,.7);
}
.hp-hero-img img{width:100%;display:block;height:440px;object-fit:cover;object-position:top center}
.hp-hero-img::after{
    content:'';position:absolute;bottom:0;left:0;right:0;height:200px;
    background:linear-gradient(transparent,var(--bg));
}

/* Stats */
.hp-stats{
    display:flex;justify-content:center;gap:0;
    border-top:1px solid var(--border);border-bottom:1px solid var(--border);flex-wrap:wrap;
}
.hp-stat{
    flex:1;min-width:140px;max-width:220px;
    padding:28px 20px;text-align:center;border-right:1px solid var(--border);
}
.hp-stat:last-child{border-right:none}
.hp-stat-val{font-size:26px;font-weight:800;letter-spacing:-.8px;color:var(--yellow)}
.hp-stat-lbl{font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.8px;margin-top:4px;font-weight:600}

/* Section */
.hp-sec{padding:72px 32px;max-width:1080px;margin:0 auto}
.hp-sec-label{font-size:11px;text-transform:uppercase;letter-spacing:1.3px;color:var(--yellow);font-weight:700;margin-bottom:10px}
.hp-sec-title{font-size:30px;font-weight:800;letter-spacing:-.8px;margin-bottom:10px}
.hp-sec-sub{font-size:14px;color:var(--text-2);margin-bottom:40px;max-width:540px;line-height:1.7}

/* Platform cards */
.hp-platforms{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
.hp-plat{
    background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-lg);
    overflow:hidden;transition:all .25s;
}
.hp-plat:hover{transform:translateY(-4px);border-color:rgba(255,200,0,.2)}
.hp-plat-img{height:160px;overflow:hidden;position:relative;background:var(--bg-3)}
.hp-plat-img img.bg-img{width:100%;height:100%;object-fit:cover;transition:transform .4s;display:block}
.hp-plat:hover .hp-plat-img img.bg-img{transform:scale(1.06)}
.hp-plat-img-overlay{
    position:absolute;inset:0;
    background:linear-gradient(transparent 20%,rgba(5,5,12,.9));
    display:flex;align-items:flex-end;padding:14px;
}
.hp-brand-badge{
    display:flex;align-items:center;gap:10px;
    background:rgba(5,5,12,.7);backdrop-filter:blur(8px);
    padding:8px 14px;border-radius:10px;border:1px solid var(--border-2);
}
.hp-brand-badge img{height:20px;width:auto;object-fit:contain}
.hp-brand-badge span{font-size:13px;font-weight:700;color:#fff}
.hp-plat-body{padding:20px}
.hp-plat-name{font-size:17px;font-weight:700;margin-bottom:6px}
.hp-plat-desc{font-size:13px;color:var(--text-2);line-height:1.65;margin-bottom:16px}
.hp-chips{display:flex;flex-wrap:wrap;gap:7px}
.hp-chip{font-size:11.5px;font-weight:600;padding:4px 11px;border-radius:20px}
.chip-amz{background:rgba(255,153,0,.1);color:#ff9900;border:1px solid rgba(255,153,0,.2)}
.chip-ppl{background:rgba(0,156,222,.1);color:#009cde;border:1px solid rgba(0,156,222,.2)}
.chip-csh{background:rgba(0,214,79,.1);color:#00d64f;border:1px solid rgba(0,214,79,.2)}
.chip-ai{background:var(--yellow-dim);color:var(--yellow);border:1px solid rgba(255,200,0,.2)}
.hp-plat-metrics{
    display:flex;gap:0;
    border-top:1px solid var(--border);background:var(--bg-3);
}
.hp-pm{flex:1;text-align:center;padding:13px 8px;border-right:1px solid var(--border)}
.hp-pm:last-child{border-right:none}
.hp-pm-val{font-size:15px;font-weight:700}
.hp-pm-lbl{font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;font-weight:600}
.amz{color:#ff9900}.ppl{color:#009cde}.csh{color:#00d64f}

/* How it works */
.hp-how{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}
.hp-how-card{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);padding:24px;transition:border-color .2s}
.hp-how-card:hover{border-color:rgba(255,200,0,.2)}
.hp-step-num{
    width:28px;height:28px;background:var(--yellow-dim);border:1px solid rgba(255,200,0,.22);
    border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:800;color:var(--yellow);margin-bottom:16px;
}
.hp-how-icon{font-size:26px;margin-bottom:10px}
.hp-how-title{font-size:14px;font-weight:700;margin-bottom:7px}
.hp-how-desc{font-size:12.5px;color:var(--text-2);line-height:1.65}

/* Trust */
.hp-trust{
    background:var(--bg-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);
    padding:48px 32px;text-align:center;
}
.hp-trust-logos{display:flex;gap:16px;justify-content:center;align-items:center;flex-wrap:wrap;margin-top:28px}
.hp-trust-logo{
    background:var(--bg-3);border:1px solid var(--border-2);border-radius:var(--r-sm);
    padding:14px 24px;display:flex;align-items:center;gap:12px;
    font-size:15px;font-weight:700;color:var(--text-2);transition:border-color .15s;
}
.hp-trust-logo:hover{border-color:var(--border-hi)}
.hp-trust-logo img{height:24px;width:auto;object-fit:contain;filter:grayscale(1) brightness(2);opacity:.55}

/* CTA */
.hp-cta{
    text-align:center;padding:92px 32px;
    background:radial-gradient(ellipse 700px 400px at 50% 50%,rgba(255,200,0,0.055),transparent);
    border-top:1px solid var(--border);
}
.hp-cta h2{font-size:36px;font-weight:800;letter-spacing:-1px;margin-bottom:14px;line-height:1.1}
.hp-cta p{font-size:15px;color:var(--text-2);margin:0 auto 36px;max-width:440px;line-height:1.65}

/* Footer */
.hp-footer{
    border-top:1px solid var(--border);padding:28px 36px;
    display:flex;justify-content:space-between;align-items:center;
    flex-wrap:wrap;gap:14px;color:var(--text-3);font-size:12.5px;
}
.hp-footer a{color:var(--text-3);text-decoration:none;transition:color .15s}
.hp-footer a:hover{color:var(--yellow)}
.hp-footer-links{display:flex;gap:22px}

.live-dot{width:7px;height:7px;background:var(--green);border-radius:50%;display:inline-block;animation:ldp 1.8s infinite;flex-shrink:0}
@keyframes ldp{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.3)}}

@media(max-width:720px){
    .hp-hero h1{font-size:32px;letter-spacing:-1px}
    .hp-hero-img img{height:220px}
    .hp-stat{min-width:50%}
    .hp-nav-links{display:none}
    .hp-footer{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="hp-nav">
    <div style="display:flex;align-items:center;gap:10px;">
        <?= advoraLogoFullSvg(36) ?>
    </div>
    <div class="hp-nav-links">
        <a href="#platforms">Platforms</a>
        <a href="#how">How It Works</a>
        <a href="/user/dashboard.php">Dashboard</a>
        <a href="#">Pricing</a>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="/login.php" class="btn btn-secondary btn-sm">Sign In</a>
        <a href="/login.php" class="btn btn-primary btn-sm">Get Started &rarr;</a>
    </div>
</nav>

<!-- HERO -->
<section class="hp-hero">
    <div class="hp-hero-badge">
        <span class="live-dot"></span>
        AI Lead Engine &mdash; Live &amp; Running
    </div>
    <h1>Generate <span>Premium Leads</span><br>&amp; Calls with AI</h1>
    <p class="hp-hero-sub">Advora's AI drives high-intent inbound calls and verified leads across Amazon, PayPal, and CashApp — powered by premium traffic that actually converts.</p>
    <div class="hp-hero-btns">
        <a href="/login.php" class="btn-xl btn-xl-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            Start Generating Leads
        </a>
        <a href="#platforms" class="btn-xl btn-xl-ghost">View Platforms</a>
    </div>
    <div class="hp-hero-img">
        <img
            src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1400&q=85&auto=format&fit=crop"
            alt="Analytics dashboard"
            loading="lazy"
        >
    </div>
</section>

<!-- STATS -->
<div class="hp-stats">
    <div class="hp-stat"><div class="hp-stat-val">4.2M+</div><div class="hp-stat-lbl">Leads Generated</div></div>
    <div class="hp-stat"><div class="hp-stat-val">98.4%</div><div class="hp-stat-lbl">Traffic Quality</div></div>
    <div class="hp-stat"><div class="hp-stat-val">$0.09</div><div class="hp-stat-lbl">Avg Cost Per Lead</div></div>
    <div class="hp-stat"><div class="hp-stat-val">3 Platforms</div><div class="hp-stat-lbl">Fully Integrated</div></div>
    <div class="hp-stat"><div class="hp-stat-val">24 / 7</div><div class="hp-stat-lbl">AI Running</div></div>
</div>

<!-- PLATFORMS -->
<div id="platforms" class="hp-sec">
    <div class="hp-sec-label">Supported Platforms</div>
    <div class="hp-sec-title">AI Calls &amp; Leads for the Biggest Platforms</div>
    <p class="hp-sec-sub">Our AI targets premium, verified audiences across the three most trusted commerce and payment networks on the internet.</p>

    <div class="hp-platforms">

        <!-- Amazon -->
        <div class="hp-plat">
            <div class="hp-plat-img">
                <img class="bg-img"
                    src="https://images.unsplash.com/photo-1523474253046-8cd2748b5fd2?w=700&q=80&auto=format&fit=crop"
                    alt="Amazon commerce"
                    loading="lazy">
                <div class="hp-plat-img-overlay">
                    <div class="hp-brand-badge">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg" alt="Amazon" style="filter:brightness(0) invert(1)">
                    </div>
                </div>
            </div>
            <div class="hp-plat-body">
                <div class="hp-plat-name">Amazon</div>
                <p class="hp-plat-desc">AI-driven buyer intent signals generate inbound calls and leads from Prime shoppers and active Amazon users — the highest-intent commerce audience online.</p>
                <div class="hp-chips">
                    <span class="hp-chip chip-amz">Buyer Intent</span>
                    <span class="hp-chip chip-amz">Inbound Calls</span>
                    <span class="hp-chip chip-ai">AI Optimised</span>
                </div>
            </div>
            <div class="hp-plat-metrics">
                <div class="hp-pm"><div class="hp-pm-val amz">2.1M</div><div class="hp-pm-lbl">Monthly Reach</div></div>
                <div class="hp-pm"><div class="hp-pm-val amz">94%</div><div class="hp-pm-lbl">Lead Quality</div></div>
                <div class="hp-pm"><div class="hp-pm-val amz">$0.09</div><div class="hp-pm-lbl">Per Lead</div></div>
            </div>
        </div>

        <!-- PayPal -->
        <div class="hp-plat">
            <div class="hp-plat-img">
                <img class="bg-img"
                    src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=700&q=80&auto=format&fit=crop"
                    alt="PayPal payments"
                    loading="lazy">
                <div class="hp-plat-img-overlay">
                    <div class="hp-brand-badge">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" style="filter:brightness(0) invert(1)">
                    </div>
                </div>
            </div>
            <div class="hp-plat-body">
                <div class="hp-plat-name">PayPal</div>
                <p class="hp-plat-desc">Reach verified PayPal users with high financial intent. Our AI targets merchants, buyers, and business accounts to deliver calls and leads that convert.</p>
                <div class="hp-chips">
                    <span class="hp-chip chip-ppl">Finance Intent</span>
                    <span class="hp-chip chip-ppl">Merchant Leads</span>
                    <span class="hp-chip chip-ai">AI Optimised</span>
                </div>
            </div>
            <div class="hp-plat-metrics">
                <div class="hp-pm"><div class="hp-pm-val ppl">1.8M</div><div class="hp-pm-lbl">Monthly Reach</div></div>
                <div class="hp-pm"><div class="hp-pm-val ppl">97%</div><div class="hp-pm-lbl">Lead Quality</div></div>
                <div class="hp-pm"><div class="hp-pm-val ppl">$0.11</div><div class="hp-pm-lbl">Per Lead</div></div>
            </div>
        </div>

        <!-- Cash App -->
        <div class="hp-plat">
            <div class="hp-plat-img">
                <img class="bg-img"
                    src="https://images.unsplash.com/photo-1607863680198-23d4b2565df0?w=700&q=80&auto=format&fit=crop"
                    alt="Cash App mobile payments"
                    loading="lazy">
                <div class="hp-plat-img-overlay">
                    <div class="hp-brand-badge">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c5/Square_Cash_app_logo.svg" alt="Cash App" style="filter:brightness(0) invert(1)">
                        <span>Cash App</span>
                    </div>
                </div>
            </div>
            <div class="hp-plat-body">
                <div class="hp-plat-name">Cash App</div>
                <p class="hp-plat-desc">Tap into Cash App's massive mobile-first user base. AI identifies active senders and receivers, generating inbound calls and leads from real payment-intent audiences.</p>
                <div class="hp-chips">
                    <span class="hp-chip chip-csh">Mobile-First</span>
                    <span class="hp-chip chip-csh">Payment Intent</span>
                    <span class="hp-chip chip-ai">AI Optimised</span>
                </div>
            </div>
            <div class="hp-plat-metrics">
                <div class="hp-pm"><div class="hp-pm-val csh">900K</div><div class="hp-pm-lbl">Monthly Reach</div></div>
                <div class="hp-pm"><div class="hp-pm-val csh">96%</div><div class="hp-pm-lbl">Lead Quality</div></div>
                <div class="hp-pm"><div class="hp-pm-val csh">$0.13</div><div class="hp-pm-lbl">Per Lead</div></div>
            </div>
        </div>

    </div>
</div>

<!-- HOW IT WORKS -->
<div id="how" class="hp-sec" style="padding-top:0">
    <div class="hp-sec-label">How It Works</div>
    <div class="hp-sec-title">From Setup to Live Leads in Minutes</div>
    <p class="hp-sec-sub">No tech expertise needed. Our AI does the heavy lifting — you just collect the leads and calls.</p>

    <div class="hp-how">
        <div class="hp-how-card">
            <div class="hp-step-num">1</div>
            <div class="hp-how-icon">🎯</div>
            <div class="hp-how-title">Choose Your Platform</div>
            <p class="hp-how-desc">Select Amazon, PayPal, Cash App — or all three. Define your target audience and campaign budget.</p>
        </div>
        <div class="hp-how-card">
            <div class="hp-step-num">2</div>
            <div class="hp-how-icon">🤖</div>
            <div class="hp-how-title">AI Builds Your Audience</div>
            <p class="hp-how-desc">Our AI analyses intent signals and identifies premium, verified users most likely to convert into leads or calls.</p>
        </div>
        <div class="hp-how-card">
            <div class="hp-step-num">3</div>
            <div class="hp-how-icon">📞</div>
            <div class="hp-how-title">Calls &amp; Leads Delivered</div>
            <p class="hp-how-desc">Inbound calls and qualified leads flow directly to your dashboard in real time. Track everything live.</p>
        </div>
        <div class="hp-how-card">
            <div class="hp-step-num">4</div>
            <div class="hp-how-icon">📈</div>
            <div class="hp-how-title">AI Keeps Optimising</div>
            <p class="hp-how-desc">The AI continuously improves targeting based on conversion data — your cost per lead drops over time automatically.</p>
        </div>
    </div>
</div>

<!-- TRUST STRIP -->
<div class="hp-trust">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1.2px;color:var(--text-3);font-weight:700;margin-bottom:6px;">Generating leads across</div>
    <div style="font-size:20px;font-weight:800;letter-spacing:-.4px;">The world's biggest platforms</div>
    <div class="hp-trust-logos">
        <div class="hp-trust-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg" alt="Amazon">
            <span>Amazon</span>
        </div>
        <div class="hp-trust-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal">
            <span>PayPal</span>
        </div>
        <div class="hp-trust-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c5/Square_Cash_app_logo.svg" alt="Cash App">
            <span>Cash App</span>
        </div>
    </div>
</div>

<!-- CTA -->
<section class="hp-cta">
    <h2>Ready to Start Generating<br><span style="color:var(--yellow)">Premium Leads?</span></h2>
    <p>Sign in to your Advora account and launch your first AI-powered lead campaign in under 5 minutes.</p>
    <a href="/login.php" class="btn-xl btn-xl-primary" style="display:inline-flex">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign In to Get Started
    </a>
</section>

<!-- FOOTER -->


</body>
</html>