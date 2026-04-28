<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/logo.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advora — Premium Traffic Infrastructure</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
:root{
    --ink:#0a0a0b;
    --ink-2:#111114;
    --ink-3:#16161a;
    --ink-4:#1d1d22;
    --line:rgba(255,255,255,0.08);
    --line-2:rgba(255,255,255,0.14);
    --line-hi:rgba(255,255,255,0.24);
    --bone:#f4f1ea;
    --bone-2:#ddd8cc;
    --dim:#7a7a82;
    --dim-2:#52525a;
    --gold:#ffc700;
    --gold-warm:#ffb000;
    --gold-soft:rgba(255,199,0,0.10);
    --signal:#34d27d;
    --blue:#5b9eff;
    --r:6px;
    --r-lg:14px;
    --sans:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    --mono:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
}

*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
    background:var(--ink);
    color:var(--bone);
    font-family:var(--sans);
    font-weight:400;
    line-height:1.5;
    -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
}

body::before{
    content:'';position:fixed;inset:0;pointer-events:none;z-index:1;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 0.06 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    opacity:.5;mix-blend-mode:overlay;
}

a{color:inherit;text-decoration:none}
img{display:block;max-width:100%}

.wrap{max-width:1280px;margin:0 auto;padding:0 32px;position:relative;z-index:2}
.eyebrow{
    font-family:var(--mono);font-size:11px;font-weight:500;
    letter-spacing:.16em;text-transform:uppercase;color:var(--dim);
}
.eyebrow .dot{
    display:inline-block;width:5px;height:5px;border-radius:50%;
    background:var(--gold);margin-right:8px;vertical-align:middle;
    box-shadow:0 0 0 0 var(--gold);animation:ping 2.4s infinite;
}
@keyframes ping{
    0%{box-shadow:0 0 0 0 rgba(255,199,0,.6)}
    70%{box-shadow:0 0 0 10px rgba(255,199,0,0)}
    100%{box-shadow:0 0 0 0 rgba(255,199,0,0)}
}

.nav{
    position:sticky;top:0;z-index:50;
    background:rgba(10,10,11,0.72);
    backdrop-filter:blur(20px) saturate(140%);
    -webkit-backdrop-filter:blur(20px) saturate(140%);
    border-bottom:1px solid var(--line);
}
.nav-inner{
    max-width:1280px;margin:0 auto;padding:0 32px;height:68px;
    display:flex;align-items:center;justify-content:space-between;gap:32px;
}
.nav-brand{display:flex;align-items:center;gap:10px}
.nav-links{display:flex;gap:4px;align-items:center}
.nav-links a{
    font-size:13px;font-weight:500;color:var(--bone-2);
    padding:8px 14px;border-radius:6px;
    transition:background .15s,color .15s;
}
.nav-links a:hover{color:var(--bone);background:var(--ink-3)}
.nav-cta{display:flex;gap:10px;align-items:center}

.btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:10px 18px;border-radius:6px;
    font-family:var(--sans);font-size:13.5px;font-weight:600;
    cursor:pointer;border:1px solid transparent;
    transition:transform .15s,background .15s,border-color .15s,box-shadow .25s;
    font-variant-numeric:tabular-nums;
    letter-spacing:-.01em;
}
.btn-ghost{background:transparent;color:var(--bone);border-color:var(--line-2)}
.btn-ghost:hover{border-color:var(--line-hi);background:var(--ink-3)}
.btn-gold{
    background:var(--gold);color:#0a0a0b;
    box-shadow:0 1px 0 rgba(255,255,255,.4) inset,0 6px 18px rgba(255,199,0,.22);
}
.btn-gold:hover{transform:translateY(-1px);box-shadow:0 1px 0 rgba(255,255,255,.4) inset,0 10px 28px rgba(255,199,0,.36)}
.btn-lg{padding:14px 24px;font-size:14px}

.hero{
    position:relative;
    padding:96px 0 80px;
    border-bottom:1px solid var(--line);
    overflow:hidden;
}
.hero::before{
    content:'';position:absolute;inset:0;
    background:
        radial-gradient(ellipse 60% 50% at 80% 20%,rgba(255,199,0,.10),transparent 60%),
        radial-gradient(ellipse 50% 40% at 10% 90%,rgba(52,210,125,.04),transparent 60%);
    pointer-events:none;
}
.hero::after{
    content:'';position:absolute;inset:0;pointer-events:none;
    background-image:
        linear-gradient(var(--line) 1px,transparent 1px),
        linear-gradient(90deg,var(--line) 1px,transparent 1px);
    background-size:64px 64px;
    mask-image:radial-gradient(ellipse 80% 70% at 50% 30%,#000,transparent 80%);
    -webkit-mask-image:radial-gradient(ellipse 80% 70% at 50% 30%,#000,transparent 80%);
    opacity:.5;
}

.hero-grid{
    position:relative;
    display:grid;grid-template-columns:1.4fr 1fr;gap:80px;align-items:center;
}
.hero-meta{
    display:flex;align-items:center;gap:14px;margin-bottom:32px;
    font-family:var(--mono);font-size:11px;letter-spacing:.14em;text-transform:uppercase;
    color:var(--dim);
}
.hero-meta .sep{width:24px;height:1px;background:var(--line-2)}

.hero h1{
    font-weight:700;
    font-size:clamp(40px,5.5vw,76px);
    line-height:1.02;
    letter-spacing:-.035em;
    margin:0 0 28px;
}
.hero h1 .accent{color:var(--gold)}
.hero-sub{
    font-size:17px;line-height:1.6;color:var(--bone-2);
    max-width:560px;margin:0 0 40px;
}
.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:48px}

.hero-trust{
    display:flex;align-items:center;gap:24px;
    padding-top:32px;border-top:1px solid var(--line);
}
.hero-trust-label{font-family:var(--mono);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--dim-2)}
.hero-trust-stack{display:flex;gap:6px;align-items:center}
.hero-trust-stack svg{width:20px;height:20px;opacity:.7}

.graph-card{
    position:relative;
    background:linear-gradient(180deg,var(--ink-2),var(--ink-3));
    border:1px solid var(--line-2);border-radius:16px;
    padding:24px;
    box-shadow:0 30px 80px rgba(0,0,0,.5),0 0 0 1px rgba(255,199,0,.04) inset;
}
.graph-card::before{
    content:'';position:absolute;inset:0;border-radius:16px;
    padding:1px;background:linear-gradient(180deg,rgba(255,199,0,.3),transparent 40%);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none;
}
.graph-head{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid var(--line);
}
.graph-title{font-family:var(--mono);font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--bone-2)}
.graph-live{
    display:flex;align-items:center;gap:6px;font-family:var(--mono);
    font-size:10.5px;color:var(--signal);letter-spacing:.12em;text-transform:uppercase;
}
.graph-live .pulse{
    width:6px;height:6px;border-radius:50%;background:var(--signal);
    box-shadow:0 0 8px var(--signal);animation:blink 1.4s infinite;
}
@keyframes blink{50%{opacity:.35}}

.traffic-viz{
    height:220px;position:relative;
    display:flex;align-items:flex-end;gap:4px;
    padding:20px 0;margin-top:12px;
}
.traffic-bar{
    flex:1;position:relative;border-radius:3px 3px 0 0;
    background:linear-gradient(180deg,var(--gold),rgba(255,199,0,.3));
    transition:height .3s cubic-bezier(.2,.8,.2,1);
}
.traffic-bar::after{
    content:attr(data-val);
    position:absolute;top:-24px;left:50%;transform:translateX(-50%);
    font-family:var(--mono);font-size:10px;color:var(--bone-2);
    white-space:nowrap;opacity:0;transition:opacity .2s;
}
.traffic-bar:hover::after{opacity:1}

.line-graph{
    height:180px;position:relative;margin-top:20px;
}
.line-graph svg{width:100%;height:100%}

.marquee{
    border-bottom:1px solid var(--line);
    padding:28px 0;background:var(--ink-2);
    overflow:hidden;position:relative;
}
.marquee::before,.marquee::after{
    content:'';position:absolute;top:0;bottom:0;width:120px;z-index:2;pointer-events:none;
}
.marquee::before{left:0;background:linear-gradient(90deg,var(--ink-2),transparent)}
.marquee::after{right:0;background:linear-gradient(-90deg,var(--ink-2),transparent)}
.marquee-track{display:flex;gap:64px;width:max-content;animation:scroll 38s linear infinite}
@keyframes scroll{to{transform:translateX(-50%)}}
.marquee-item{
    display:flex;align-items:center;gap:14px;
    font-size:18px;font-weight:500;
    color:var(--bone-2);letter-spacing:-.01em;white-space:nowrap;
}
.marquee-item .pill{
    font-family:var(--mono);font-size:10.5px;font-weight:600;
    background:var(--gold-soft);color:var(--gold);
    padding:3px 9px;border-radius:20px;letter-spacing:.06em;
    border:1px solid rgba(255,199,0,.18);
}
.marquee-item .ast{color:var(--gold);font-size:18px}

.sec{padding:120px 0;border-bottom:1px solid var(--line);position:relative}
.sec-head{
    display:grid;grid-template-columns:1fr 2fr;gap:64px;
    margin-bottom:72px;align-items:end;
}
.sec-head h2{
    font-weight:700;
    font-size:clamp(32px,4vw,52px);
    line-height:1.05;letter-spacing:-.025em;
    margin:14px 0 0;
}
.sec-head h2 .accent{color:var(--gold)}
.sec-head p{font-size:15.5px;line-height:1.6;color:var(--bone-2);max-width:520px;margin:0}

.metrics{
    display:grid;grid-template-columns:repeat(4,1fr);
    border:1px solid var(--line);border-radius:var(--r-lg);
    background:var(--ink-2);overflow:hidden;
}
.metric{
    padding:36px 28px;position:relative;
    border-right:1px solid var(--line);
    transition:background .25s;
}
.metric:last-child{border-right:none}
.metric:hover{background:var(--ink-3)}
.metric-num{
    font-weight:700;
    font-size:48px;line-height:1;letter-spacing:-.04em;
    color:var(--bone);margin-bottom:14px;
    font-variant-numeric:tabular-nums;
}
.metric-num .unit{font-size:26px;color:var(--gold);margin-left:2px}
.metric-label{
    font-family:var(--mono);font-size:11px;letter-spacing:.12em;
    text-transform:uppercase;color:var(--dim);margin-bottom:14px;
}
.metric-bar{height:2px;background:var(--ink-4);border-radius:2px;overflow:hidden}
.metric-bar i{display:block;height:100%;background:var(--gold);transform-origin:left;animation:fill 1.6s ease-out}
@keyframes fill{from{transform:scaleX(0)}}

.features{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.feature{
    position:relative;
    background:var(--ink-2);
    border:1px solid var(--line);border-radius:var(--r-lg);
    padding:32px;overflow:hidden;
    transition:transform .3s cubic-bezier(.2,.7,.2,1),border-color .25s;
}
.feature::before{
    content:'';position:absolute;inset:0;pointer-events:none;
    background:radial-gradient(ellipse 80% 60% at 50% -20%,rgba(255,199,0,.08),transparent 50%);
    opacity:0;transition:opacity .35s;
}
.feature:hover{transform:translateY(-4px);border-color:var(--line-hi)}
.feature:hover::before{opacity:.5}

.feature-icon{
    width:48px;height:48px;margin-bottom:24px;
    display:flex;align-items:center;justify-content:center;
    background:var(--gold-soft);border:1px solid rgba(255,199,0,.2);
    border-radius:12px;
}
.feature-icon svg{width:24px;height:24px;color:var(--gold)}
.feature-name{font-weight:700;font-size:20px;letter-spacing:-.015em;margin-bottom:12px}
.feature-desc{font-size:14px;line-height:1.6;color:var(--bone-2)}

.integrations{
    display:grid;grid-template-columns:repeat(4,1fr);gap:16px;
    margin-top:48px;
}
.integration{
    background:var(--ink-3);
    border:1px solid var(--line);border-radius:12px;
    padding:24px;text-align:center;
    transition:border-color .2s,background .2s;
}
.integration:hover{border-color:var(--line-hi);background:var(--ink-4)}
.integration-icon{
    width:40px;height:40px;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;
}
.integration-icon svg{width:28px;height:28px}
.integration-name{font-family:var(--mono);font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--bone-2)}

.ai-grid{
    display:grid;grid-template-columns:repeat(3,1fr);gap:24px;
    margin-bottom:64px;
}
.ai-card{
    background:linear-gradient(180deg,var(--ink-2),var(--ink-3));
    border:1px solid var(--line-2);border-radius:16px;
    padding:24px;position:relative;
    box-shadow:0 20px 60px rgba(0,0,0,.4);
}
.ai-card::before{
    content:'';position:absolute;inset:0;border-radius:16px;
    padding:1px;background:linear-gradient(180deg,rgba(255,199,0,.2),transparent 40%);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none;
}
.ai-card-head{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--line);
}
.ai-card-title{font-family:var(--mono);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--bone-2)}
.ai-card-status{display:flex;align-items:center;gap:6px}

.ai-score-viz{
    height:180px;display:flex;align-items:center;justify-content:center;
    position:relative;margin:20px 0;
}
.ai-score-circle{
    width:140px;height:140px;position:relative;
}
.ai-score-bg,.ai-score-fill{
    fill:none;stroke-width:12;
    transform:rotate(-90deg);transform-origin:50% 50%;
}
.ai-score-bg{stroke:var(--ink-4)}
.ai-score-fill{
    stroke:var(--gold);
    stroke-linecap:round;
    animation:fillScore 2s ease-out forwards;
}
@keyframes fillScore{
    from{stroke-dashoffset:440}
    to{stroke-dashoffset:26}
}
.ai-score-text{
    position:absolute;inset:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;
}
.ai-score-num{
    font-weight:700;font-size:42px;letter-spacing:-.03em;
    color:var(--gold);line-height:1;
}
.ai-score-label{
    font-family:var(--mono);font-size:9px;letter-spacing:.12em;
    text-transform:uppercase;color:var(--dim);margin-top:4px;
}

.ai-card-meta{
    display:grid;grid-template-columns:repeat(3,1fr);gap:1px;
    background:var(--line);border-radius:8px;overflow:hidden;
    margin-top:20px;
}
.ai-meta-item{padding:14px 12px;background:var(--ink-3);text-align:center}
.ai-meta-val{
    font-family:var(--mono);font-size:16px;font-weight:600;
    color:var(--bone);letter-spacing:-.01em;
}
.ai-meta-lbl{
    font-family:var(--mono);font-size:9px;letter-spacing:.1em;
    text-transform:uppercase;color:var(--dim);margin-top:4px;
}

.quality-chart{
    height:160px;display:flex;align-items:flex-end;gap:8px;
    padding:20px 0;
}
.quality-bar{
    flex:1;border-radius:6px 6px 0 0;
    transition:height .6s cubic-bezier(.2,.8,.2,1);
}
.quality-legend{
    display:flex;gap:16px;margin-top:20px;padding-top:16px;
    border-top:1px solid var(--line);flex-wrap:wrap;
}
.legend-item{
    display:flex;align-items:center;gap:6px;
    font-family:var(--mono);font-size:10.5px;color:var(--bone-2);
}
.legend-dot{
    width:8px;height:8px;border-radius:50%;
}

.signals-list{display:flex;flex-direction:column;gap:16px;padding:12px 0}
.signal-item{position:relative}
.signal-bar{
    height:32px;background:linear-gradient(90deg,var(--gold),rgba(255,199,0,.2));
    border-radius:4px;width:var(--width);
    transition:width .8s cubic-bezier(.2,.8,.2,1);
}
.signal-label{
    position:absolute;left:12px;top:50%;transform:translateY(-50%);
    font-size:11.5px;color:var(--ink);font-weight:600;
    mix-blend-mode:multiply;
}
.signal-value{
    position:absolute;right:12px;top:50%;transform:translateY(-50%);
    font-family:var(--mono);font-size:11px;font-weight:700;
    color:var(--gold-warm);
}

.ai-features{
    display:grid;grid-template-columns:repeat(4,1fr);gap:20px;
    margin-top:48px;
}
.ai-feat{
    background:var(--ink-2);border:1px solid var(--line);
    border-radius:12px;padding:28px 24px;
    transition:border-color .2s,transform .2s;
}
.ai-feat:hover{border-color:var(--line-hi);transform:translateY(-2px)}
.ai-feat-icon{
    width:44px;height:44px;margin-bottom:18px;
    display:flex;align-items:center;justify-content:center;
    background:var(--gold-soft);border:1px solid rgba(255,199,0,.2);
    border-radius:10px;
}
.ai-feat-icon svg{width:22px;height:22px;color:var(--gold)}
.ai-feat-name{
    font-weight:700;font-size:15px;letter-spacing:-.01em;
    margin-bottom:8px;color:var(--bone);
}
.ai-feat-desc{
    font-size:13px;line-height:1.5;color:var(--bone-2);
}

.quality-grid{
    display:grid;grid-template-columns:1.6fr 1fr;gap:32px;
    margin-bottom:48px;
}
.quality-main{
    background:linear-gradient(180deg,var(--ink-2),var(--ink-3));
    border:1px solid var(--line-2);border-radius:16px;
    padding:40px;
    box-shadow:0 20px 60px rgba(0,0,0,.4);
}

.quality-flow{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:48px;padding-bottom:48px;
    border-bottom:1px solid var(--line);
}
.flow-node{
    text-align:center;opacity:0;
    animation:fadeInUp .6s ease-out forwards;
}
.flow-node[data-step="1"]{animation-delay:.2s}
.flow-node[data-step="2"]{animation-delay:.6s}
.flow-node[data-step="3"]{animation-delay:1s}

@keyframes fadeInUp{
    from{opacity:0;transform:translateY(20px)}
    to{opacity:1;transform:translateY(0)}
}

.flow-icon{
    width:64px;height:64px;margin:0 auto 12px;
    background:var(--gold-soft);border:2px solid rgba(255,199,0,.3);
    border-radius:50%;display:flex;align-items:center;justify-content:center;
    position:relative;
}
.flow-icon svg{width:28px;height:28px;color:var(--gold)}
.flow-icon::before{
    content:'';position:absolute;inset:-4px;
    border:2px solid var(--gold);border-radius:50%;
    opacity:0;animation:pulse 2s infinite;
}
@keyframes pulse{
    0%,100%{opacity:0;transform:scale(1)}
    50%{opacity:.3;transform:scale(1.1)}
}

.flow-label{
    font-family:var(--mono);font-size:11px;letter-spacing:.12em;
    text-transform:uppercase;color:var(--bone-2);margin-bottom:6px;
}
.flow-count{
    font-family:var(--mono);font-size:14px;font-weight:600;
    color:var(--gold);
}

.flow-arrow{
    width:100px;opacity:0;
    animation:fadeIn .4s ease-out forwards;
}
.flow-arrow:first-of-type{animation-delay:.4s}
.flow-arrow:last-of-type{animation-delay:.8s}
.flow-arrow svg{width:100%;height:40px}
.flow-arrow path:first-child{
    animation:dash 1.5s linear infinite;
    stroke-dashoffset:0;
}
@keyframes dash{
    to{stroke-dashoffset:-20}
}
@keyframes fadeIn{
    from{opacity:0}
    to{opacity:1}
}

.quality-stats{
    display:grid;grid-template-columns:repeat(3,1fr);gap:20px;
}
.quality-stat{
    display:flex;align-items:center;gap:14px;
    padding:20px;background:var(--ink-4);
    border:1px solid var(--line);border-radius:10px;
}
.stat-icon{
    width:44px;height:44px;flex-shrink:0;
    border-radius:50%;display:flex;align-items:center;justify-content:center;
}
.stat-icon.rejected{
    background:rgba(239,68,68,.1);border:2px solid rgba(239,68,68,.3);
}
.stat-icon svg{width:20px;height:20px;color:#ef4444}
.stat-num{
    font-weight:700;font-size:24px;letter-spacing:-.02em;
    color:var(--bone);line-height:1;margin-bottom:4px;
}
.stat-label{
    font-family:var(--mono);font-size:10px;letter-spacing:.1em;
    text-transform:uppercase;color:var(--dim);
}

.quality-checks{
    background:linear-gradient(180deg,var(--ink-2),var(--ink-3));
    border:1px solid var(--line-2);border-radius:16px;
    padding:32px;
}
.check-title{
    font-weight:700;font-size:18px;letter-spacing:-.01em;
    margin-bottom:24px;padding-bottom:20px;
    border-bottom:1px solid var(--line);
}
.check-list{display:flex;flex-direction:column;gap:16px}
.check-item{
    display:flex;align-items:center;gap:12px;
    padding:14px;background:var(--ink-4);
    border:1px solid var(--line);border-radius:8px;
    transition:border-color .2s,background .2s;
}
.check-item:hover{border-color:var(--line-hi);background:var(--ink-3)}
.check-icon{
    width:32px;height:32px;flex-shrink:0;
    border-radius:50%;background:rgba(52,210,125,.1);
    display:flex;align-items:center;justify-content:center;
}
.check-icon svg{width:18px;height:18px;color:var(--signal)}
.check-content{flex:1}
.check-name{
    font-size:13px;font-weight:600;letter-spacing:-.01em;
    color:var(--bone);margin-bottom:2px;
}
.check-desc{
    font-family:var(--mono);font-size:10px;
    color:var(--dim);letter-spacing:.06em;
}
.check-status{
    font-family:var(--mono);font-size:9.5px;font-weight:600;
    letter-spacing:.12em;text-transform:uppercase;
    color:var(--signal);padding:4px 10px;
    background:rgba(52,210,125,.1);border:1px solid rgba(52,210,125,.2);
    border-radius:20px;
}

.quality-guarantee{
    display:flex;align-items:center;gap:32px;
    background:linear-gradient(135deg,var(--ink-2),var(--ink-3));
    border:2px solid var(--gold);border-radius:16px;
    padding:36px 40px;position:relative;overflow:hidden;
}
.quality-guarantee::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse 80% 100% at 0% 50%,rgba(255,199,0,.08),transparent 60%);
    pointer-events:none;
}
.guarantee-badge{
    width:80px;height:80px;flex-shrink:0;
    background:var(--gold);border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    position:relative;
    box-shadow:0 8px 24px rgba(255,199,0,.3);
}
.guarantee-badge svg{width:40px;height:40px;color:var(--ink)}
.guarantee-badge::after{
    content:'';position:absolute;inset:-6px;
    border:3px solid var(--gold);border-radius:50%;
    opacity:.3;animation:ripple 2s infinite;
}
@keyframes ripple{
    0%{transform:scale(1);opacity:.3}
    100%{transform:scale(1.2);opacity:0}
}
.guarantee-content{position:relative;z-index:2}
.guarantee-title{
    font-weight:700;font-size:24px;letter-spacing:-.02em;
    color:var(--gold);margin-bottom:10px;
}
.guarantee-text{
    font-size:14.5px;line-height:1.6;color:var(--bone-2);
    max-width:720px;
}

.cta{
    padding:140px 0;
    background:
        radial-gradient(ellipse 60% 80% at 50% 0%,rgba(255,199,0,.08),transparent),
        var(--ink);
    border-bottom:1px solid var(--line);text-align:center;position:relative;
}
.cta::before{
    content:'';position:absolute;inset:0;pointer-events:none;
    background-image:linear-gradient(var(--line) 1px,transparent 1px);
    background-size:1px 48px;
    mask-image:radial-gradient(ellipse 70% 80% at 50% 50%,#000,transparent);
    -webkit-mask-image:radial-gradient(ellipse 70% 80% at 50% 50%,#000,transparent);
    opacity:.4;
}
.cta-inner{position:relative;z-index:2}
.cta h2{
    font-weight:700;
    font-size:clamp(40px,5.5vw,76px);
    line-height:1.02;letter-spacing:-.035em;
    margin:0 auto 24px;max-width:880px;
}
.cta h2 .accent{color:var(--gold)}
.cta p{font-size:16px;color:var(--bone-2);max-width:520px;margin:0 auto 40px;line-height:1.6}

.foot-mini{
    background:var(--ink);
    border-top:1px solid var(--line);
}

.foot-showcase{
    position:relative;
    max-width:960px;
    margin:0 auto;
    padding:48px 32px 24px;
}
.foot-showcase img{
    width:100%;
    aspect-ratio:21/9;
    object-fit:cover;
    display:block;
    border-radius:16px;
    border:1px solid var(--line-2);
    box-shadow:
        0 20px 60px rgba(0,0,0,.5),
        0 0 80px rgba(255,199,0,.06);
    color:transparent;font-size:0;
    background:
        radial-gradient(ellipse 80% 100% at 50% 100%,rgba(255,199,0,.12),transparent 60%),
        linear-gradient(180deg,var(--ink-2),var(--ink-3));
}

.foot-mini-inner{
    max-width:1280px;margin:0 auto;
    padding:24px 32px;
    display:flex;justify-content:center;align-items:center;
}
.foot-dev{
    display:flex;align-items:center;gap:14px;
    padding:8px 14px;background:var(--ink-2);border:1px solid var(--line);
    border-radius:8px;
}
.foot-dev-label{
    font-family:var(--mono);font-size:10px;letter-spacing:.14em;
    text-transform:uppercase;color:var(--dim-2);
}
.foot-dev-links{display:flex;gap:14px;align-items:center}
.foot-dev-links a{
    display:inline-flex;align-items:center;gap:6px;
    font-size:12.5px;font-weight:500;color:var(--bone-2);
    transition:color .15s;
}
.foot-dev-links a:hover{color:var(--gold)}
.foot-dev-links svg{width:14px;height:14px}
.foot-dev-sep{color:var(--dim-2)}

.reveal{opacity:0;transform:translateY(24px);transition:opacity .8s,transform .8s}
.reveal.in{opacity:1;transform:none}

@media(max-width:1024px){
    .wrap{padding:0 28px}
    .nav-inner{padding:0 28px;gap:20px}
    .nav-links{display:none}

    .hero{padding:72px 0 64px}
    .hero-grid{grid-template-columns:1fr;gap:48px}
    .graph-card{max-width:520px}

    .sec{padding:96px 0}
    .sec-head{grid-template-columns:1fr;gap:20px;margin-bottom:48px}

    .metrics{grid-template-columns:repeat(2,1fr)}
    .metric:nth-child(2){border-right:none}
    .metric:nth-child(1),.metric:nth-child(2){border-bottom:1px solid var(--line)}

    .features{grid-template-columns:1fr;gap:16px}
    .integrations{grid-template-columns:repeat(2,1fr)}
    .ai-grid{grid-template-columns:1fr;gap:20px}
    .ai-features{grid-template-columns:repeat(2,1fr);gap:16px}

    .quality-grid{grid-template-columns:1fr;gap:20px}
    .quality-main{padding:28px}
    .quality-flow{flex-direction:column;gap:20px;margin-bottom:32px;padding-bottom:32px}
    .flow-arrow{transform:rotate(90deg);width:40px;margin:0}
    .quality-stats{grid-template-columns:1fr;gap:14px}
    .quality-checks{padding:24px}
    .quality-guarantee{flex-direction:column;text-align:center;padding:28px}
    .guarantee-badge{margin-bottom:10px}

    .cta{padding:96px 0}
    .foot-showcase{padding:32px 24px 16px;max-width:100%}
}

@media(max-width:640px){
    .wrap{padding:0 18px}
    .nav-inner{padding:0 18px;height:56px;gap:8px}
    .nav-brand svg{height:28px !important;width:auto !important}
    .btn{padding:9px 14px;font-size:12.5px}
    .btn-lg{padding:12px 18px;font-size:13px}
    .nav-cta .btn-ghost{display:none}

    .hero{padding:48px 0 40px}
    .hero-meta{flex-wrap:wrap;gap:8px;margin-bottom:20px}
    .hero-meta .sep{display:none}
    .hero h1{font-size:38px;line-height:1.05;margin-bottom:20px}
    .hero-sub{font-size:15px;margin-bottom:28px}
    .hero-actions{gap:10px;margin-bottom:32px;flex-direction:column;align-items:stretch}
    .hero-actions .btn{justify-content:center;width:100%}
    .hero-trust{gap:14px;flex-wrap:wrap;padding-top:24px}
    .hero-trust-label{font-size:10px}

    .graph-card{padding:18px;border-radius:14px}
    .traffic-viz{height:160px}

    .marquee{padding:18px 0}
    .marquee-track{gap:36px}
    .marquee-item{font-size:14px;gap:10px}
    .marquee-item .pill{font-size:9.5px;padding:2px 7px}

    .sec{padding:64px 0}
    .sec-head{margin-bottom:36px;gap:14px}
    .sec-head h2{font-size:30px;line-height:1.1}

    .metrics{grid-template-columns:1fr;border-radius:12px}
    .metric{padding:24px 20px;border-right:none;border-bottom:1px solid var(--line)}
    .metric:last-child{border-bottom:none}
    .metric-num{font-size:38px;margin-bottom:10px}
    .metric-num .unit{font-size:22px}

    .feature{padding:24px;border-radius:12px}
    .feature-icon{width:40px;height:40px;margin-bottom:18px}
    .feature-name{font-size:18px}
    .feature-desc{font-size:13.5px}

    .integrations{grid-template-columns:1fr}
    .integration{padding:20px}

    .ai-grid{gap:16px}
    .ai-card{padding:20px;border-radius:12px}
    .ai-score-viz{height:140px}
    .ai-score-circle{width:110px;height:110px}
    .ai-score-num{font-size:34px}
    .quality-chart{height:120px}
    .signals-list{gap:12px}
    .signal-bar{height:28px}
    .signal-label{font-size:10.5px;left:10px}
    .signal-value{font-size:10px;right:10px}
    .ai-features{grid-template-columns:1fr;gap:12px}
    .ai-feat{padding:20px}
    .ai-feat-icon{width:38px;height:38px;margin-bottom:14px}
    .ai-feat-name{font-size:14px}
    .ai-feat-desc{font-size:12.5px}

    .quality-main{padding:20px}
    .quality-flow{gap:16px;margin-bottom:24px;padding-bottom:24px}
    .flow-icon{width:52px;height:52px}
    .flow-icon svg{width:24px;height:24px}
    .flow-label{font-size:10px}
    .flow-count{font-size:12px}
    .quality-stat{padding:16px;gap:12px}
    .stat-icon{width:38px;height:38px}
    .stat-num{font-size:20px}
    .stat-label{font-size:9px}
    .quality-checks{padding:20px}
    .check-title{font-size:16px;margin-bottom:18px;padding-bottom:16px}
    .check-item{padding:12px;gap:10px}
    .check-icon{width:28px;height:28px}
    .check-icon svg{width:16px;height:16px}
    .check-name{font-size:12px}
    .check-desc{font-size:9.5px}
    .check-status{font-size:9px;padding:3px 8px}
    .quality-guarantee{gap:20px;padding:24px 20px}
    .guarantee-badge{width:64px;height:64px}
    .guarantee-badge svg{width:32px;height:32px}
    .guarantee-title{font-size:20px;margin-bottom:8px}
    .guarantee-text{font-size:13px}

    .cta{padding:72px 0}
    .cta h2{font-size:36px;line-height:1.05;margin-bottom:18px}
    .cta p{font-size:14.5px;margin-bottom:28px}

    .foot-showcase{padding:24px 16px 12px}
    .foot-showcase img{border-radius:12px;aspect-ratio:16/9}
    .foot-mini-inner{padding:18px}
    .foot-dev{padding:8px 12px;gap:10px;flex-wrap:wrap;justify-content:center}
}

@media(max-width:380px){
    .wrap{padding:0 14px}
    .nav-inner{padding:0 14px}
    .hero h1{font-size:32px}
    .sec-head h2{font-size:26px}
    .cta h2{font-size:30px}
    .metric-num{font-size:32px}
}
</style>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="/" class="nav-brand">
            <?= advoraLogoFullSvg(36) ?>
        </a>
        <div class="nav-cta">
            <a href="/login.php" class="btn btn-gold">Login</a>
        </div>
    </div>
</nav>

<header class="hero">
    <div class="wrap hero-grid">
        <div>
            <div class="hero-meta">
                <span class="eyebrow"><span class="dot"></span>Live</span>
                <span class="sep"></span>
                <span class="eyebrow">Premium Traffic</span>
                <span class="sep"></span>
                <span class="eyebrow">Real-Time Analytics</span>
            </div>

            <h1>
                Premium traffic<br>
                that drives <span class="accent">real results</span>.
            </h1>

            <p class="hero-sub">
                AI-powered traffic infrastructure that learns and adapts in real-time. 
                Our neural network analyzes billions of signals to deliver verified, 
                high-intent visitors that actually convert.
            </p>

            <div class="hero-actions">
                <a href="#contact" class="btn btn-gold btn-lg">
                    Start driving traffic
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
                <a href="#metrics" class="btn btn-ghost btn-lg">View analytics</a>
            </div>

            <div class="hero-trust">
                <div class="hero-trust-stack">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="hero-trust-label">
                    Verified quality traffic<br>
                    <span style="color:var(--bone-2)">&mdash; 2.8B impressions monthly</span>
                </div>
            </div>
        </div>

        <aside class="graph-card reveal">
            <div class="graph-head">
                <div class="graph-title">Traffic Performance</div>
                <div class="graph-live"><span class="pulse"></span>Live</div>
            </div>

            <div class="traffic-viz" id="trafficViz"></div>
        </aside>
    </div>
</header>

<div class="marquee">
    <div class="marquee-track">
        <div class="marquee-item"><span class="pill">AI</span>Neural traffic routing <span class="ast">&#10022;</span></div>
        <div class="marquee-item">Real-time learning engine <span class="ast">&#10022;</span></div>
        <div class="marquee-item"><span class="pill">ML</span>Predictive conversion scoring <span class="ast">&#10022;</span></div>
        <div class="marquee-item">Smart fraud detection <span class="ast">&#10022;</span></div>
        <div class="marquee-item"><span class="pill">AUTO</span>Self-optimizing campaigns <span class="ast">&#10022;</span></div>
        <div class="marquee-item">99.7% bot filtering accuracy <span class="ast">&#10022;</span></div>
        <div class="marquee-item"><span class="pill">AI</span>Neural traffic routing <span class="ast">&#10022;</span></div>
        <div class="marquee-item">Real-time learning engine <span class="ast">&#10022;</span></div>
        <div class="marquee-item"><span class="pill">ML</span>Predictive conversion scoring <span class="ast">&#10022;</span></div>
        <div class="marquee-item">Smart fraud detection <span class="ast">&#10022;</span></div>
        <div class="marquee-item"><span class="pill">AUTO</span>Self-optimizing campaigns <span class="ast">&#10022;</span></div>
        <div class="marquee-item">99.7% bot filtering accuracy <span class="ast">&#10022;</span></div>
    </div>
</div>

<section id="metrics" class="sec">
    <div class="wrap">
        <div class="sec-head reveal">
            <div class="eyebrow"><span class="dot"></span>Real-Time Metrics</div>
            <div>
                <h2>Live traffic<br><span class="accent">analytics</span>.</h2>
            </div>
        </div>

        <div class="metrics reveal">
            <div class="metric">
                <div class="metric-label">Monthly traffic</div>
                <div class="metric-num">2.8<span class="unit">B+</span></div>
                <div class="metric-bar"><i style="width:96%"></i></div>
            </div>
            <div class="metric">
                <div class="metric-label">Quality score</div>
                <div class="metric-num">98<span class="unit">%</span></div>
                <div class="metric-bar"><i style="width:98%"></i></div>
            </div>
            <div class="metric">
                <div class="metric-label">Avg. engagement</div>
                <div class="metric-num">3.2<span class="unit">min</span></div>
                <div class="metric-bar"><i style="width:85%"></i></div>
            </div>
            <div class="metric">
                <div class="metric-label">Active campaigns</div>
                <div class="metric-num">4.8<span class="unit">k</span></div>
                <div class="metric-bar"><i style="width:88%"></i></div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="sec">
    <div class="wrap">
        <div class="sec-head reveal">
            <div class="eyebrow"><span class="dot"></span>Features</div>
            <div>
                <h2>Built for<br><span class="accent">performance</span>.</h2>
                <p>Everything you need to drive quality traffic and measure results with precision.</p>
            </div>
        </div>

        <div class="features">
            <article class="feature reveal">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="feature-name">Verified Quality</h3>
                <p class="feature-desc">Every visitor is human-verified with anti-bot technology. Real engagement, real results&mdash;no fake clicks or bots.</p>
            </article>

            <article class="feature reveal">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="feature-name">Instant Delivery</h3>
                <p class="feature-desc">Traffic starts flowing within minutes of campaign launch. Real-time dashboard shows every visitor, click, and conversion.</p>
            </article>

            <article class="feature reveal">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="feature-name">Deep Analytics</h3>
                <p class="feature-desc">Track every metric that matters. Geographic data, device types, session duration, bounce rate, and conversion tracking built-in.</p>
            </article>

            <article class="feature reveal">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                </div>
                <h3 class="feature-name">Smart Targeting</h3>
                <p class="feature-desc">AI-powered targeting delivers visitors most likely to convert. Location, interest, behavior&mdash;optimized automatically.</p>
            </article>

            <article class="feature reveal">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="feature-name">Cost Effective</h3>
                <p class="feature-desc">Pay only for quality traffic. Transparent pricing with no hidden fees. Scale up or down anytime without contracts.</p>
            </article>

            <article class="feature reveal">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <h3 class="feature-name">Fraud Protection</h3>
                <p class="feature-desc">Multi-layer fraud detection filters out bots, proxies, and invalid traffic. Only real visitors reach your site.</p>
            </article>
        </div>
    </div>
</section>

<section id="quality" class="sec">
    <div class="wrap">
        <div class="sec-head reveal">
            <div class="eyebrow"><span class="dot"></span>Traffic Quality</div>
            <div>
                <h2>Every visitor<br><span class="accent">verified & tracked</span>.</h2>
                <p>Multi-layer verification ensures only genuine, high-intent traffic reaches your campaigns. Zero tolerance for bots, proxies, or low-quality sources.</p>
            </div>
        </div>

        <div class="quality-grid">
            <div class="quality-main reveal">
                <div class="quality-flow">
                    <div class="flow-node" data-step="1">
                        <div class="flow-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                        </div>
                        <div class="flow-label">Incoming Traffic</div>
                        <div class="flow-count">2.8B/month</div>
                    </div>

                    <div class="flow-arrow">
                        <svg viewBox="0 0 100 40" fill="none">
                            <path d="M0 20 L85 20" stroke="var(--gold)" stroke-width="2" stroke-dasharray="4 4"/>
                            <path d="M80 15 L95 20 L80 25" fill="var(--gold)"/>
                        </svg>
                    </div>

                    <div class="flow-node" data-step="2">
                        <div class="flow-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </div>
                        <div class="flow-label">AI Verification</div>
                        <div class="flow-count">99.7% accuracy</div>
                    </div>

                    <div class="flow-arrow">
                        <svg viewBox="0 0 100 40" fill="none">
                            <path d="M0 20 L85 20" stroke="var(--gold)" stroke-width="2" stroke-dasharray="4 4"/>
                            <path d="M80 15 L95 20 L80 25" fill="var(--gold)"/>
                        </svg>
                    </div>

                    <div class="flow-node" data-step="3">
                        <div class="flow-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flow-label">Quality Traffic</div>
                        <div class="flow-count">2.7B delivered</div>
                    </div>
                </div>

                <div class="quality-stats">
                    <div class="quality-stat">
                        <div class="stat-icon rejected">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div class="stat-data">
                            <div class="stat-num">58M</div>
                            <div class="stat-label">Bots filtered</div>
                        </div>
                    </div>
                    <div class="quality-stat">
                        <div class="stat-icon rejected">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div class="stat-data">
                            <div class="stat-num">34M</div>
                            <div class="stat-label">Proxies blocked</div>
                        </div>
                    </div>
                    <div class="quality-stat">
                        <div class="stat-icon rejected">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div class="stat-data">
                            <div class="stat-num">12M</div>
                            <div class="stat-label">Low-quality rejected</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="quality-checks reveal">
                <div class="check-title">Real-Time Verification Layers</div>
                <div class="check-list">
                    <div class="check-item">
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="check-content">
                            <div class="check-name">Device Fingerprinting</div>
                            <div class="check-desc">Hardware & browser signature analysis</div>
                        </div>
                        <div class="check-status">Active</div>
                    </div>

                    <div class="check-item">
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="check-content">
                            <div class="check-name">Behavioral Analysis</div>
                            <div class="check-desc">Mouse movement & scroll patterns</div>
                        </div>
                        <div class="check-status">Active</div>
                    </div>

                    <div class="check-item">
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="check-content">
                            <div class="check-name">IP Reputation Check</div>
                            <div class="check-desc">Datacenter & VPN detection</div>
                        </div>
                        <div class="check-status">Active</div>
                    </div>

                    <div class="check-item">
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="check-content">
                            <div class="check-name">Session Validation</div>
                            <div class="check-desc">Real-time engagement scoring</div>
                        </div>
                        <div class="check-status">Active</div>
                    </div>

                    <div class="check-item">
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="check-content">
                            <div class="check-name">Geographic Validation</div>
                            <div class="check-desc">Location consistency checks</div>
                        </div>
                        <div class="check-status">Active</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="quality-guarantee reveal">
            <div class="guarantee-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                </svg>
            </div>
            <div class="guarantee-content">
                <div class="guarantee-title">Quality Guarantee</div>
                <div class="guarantee-text">
                    Every visitor is human-verified with our AI system. If invalid traffic is detected post-delivery, 
                    we automatically issue credits. No questions asked. Your campaign performance is our priority.
                </div>
            </div>
        </div>
    </div>
</section>

<section id="ai-system" class="sec">
    <div class="wrap">
        <div class="sec-head reveal">
            <div class="eyebrow"><span class="dot"></span>AI-Powered Targeting</div>
            <div>
                <h2>Smart AI routes traffic<br>to <span class="accent">maximize conversions</span>.</h2>
                <p>Our neural network analyzes billions of data points in real-time to deliver the most relevant, high-converting traffic to your campaigns.</p>
            </div>
        </div>

        <div class="ai-grid">
            <div class="ai-card reveal">
                <div class="ai-card-head">
                    <div class="ai-card-title">AI Optimization Score</div>
                    <div class="ai-card-status">
                        <span class="pulse"></span>
                        <span style="font-family:var(--mono);font-size:10px;letter-spacing:.12em;color:var(--signal)">LEARNING</span>
                    </div>
                </div>
                <div class="ai-score-viz" id="aiScore"></div>
                <div class="ai-card-meta">
                    <div class="ai-meta-item">
                        <div class="ai-meta-val">98.4%</div>
                        <div class="ai-meta-lbl">Accuracy</div>
                    </div>
                    <div class="ai-meta-item">
                        <div class="ai-meta-val">2.8M</div>
                        <div class="ai-meta-lbl">Data points/sec</div>
                    </div>
                    <div class="ai-meta-item">
                        <div class="ai-meta-val">47ms</div>
                        <div class="ai-meta-lbl">Response time</div>
                    </div>
                </div>
            </div>

            <div class="ai-card reveal">
                <div class="ai-card-head">
                    <div class="ai-card-title">Traffic Quality Distribution</div>
                    <div class="graph-live"><span class="pulse"></span>Live</div>
                </div>
                <div class="quality-chart" id="qualityChart"></div>
                <div class="quality-legend">
                    <div class="legend-item">
                        <span class="legend-dot" style="background:var(--signal)"></span>
                        <span>Premium (87%)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background:var(--gold)"></span>
                        <span>Standard (11%)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background:var(--dim)"></span>
                        <span>Filtered (2%)</span>
                    </div>
                </div>
            </div>

            <div class="ai-card reveal">
                <div class="ai-card-head">
                    <div class="ai-card-title">Real-Time Targeting Signals</div>
                    <div class="graph-live"><span class="pulse"></span>Processing</div>
                </div>
                <div class="signals-list">
                    <div class="signal-item">
                        <div class="signal-bar" style="--width:94%"></div>
                        <div class="signal-label">Device fingerprint</div>
                        <div class="signal-value">94%</div>
                    </div>
                    <div class="signal-item">
                        <div class="signal-bar" style="--width:88%"></div>
                        <div class="signal-label">Behavioral pattern</div>
                        <div class="signal-value">88%</div>
                    </div>
                    <div class="signal-item">
                        <div class="signal-bar" style="--width:91%"></div>
                        <div class="signal-label">Geographic intent</div>
                        <div class="signal-value">91%</div>
                    </div>
                    <div class="signal-item">
                        <div class="signal-bar" style="--width:85%"></div>
                        <div class="signal-label">Time-based scoring</div>
                        <div class="signal-value">85%</div>
                    </div>
                    <div class="signal-item">
                        <div class="signal-bar" style="--width:96%"></div>
                        <div class="signal-label">Bot detection</div>
                        <div class="signal-value">96%</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ai-features reveal">
            <div class="ai-feat">
                <div class="ai-feat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <div class="ai-feat-name">Neural Traffic Routing</div>
                <div class="ai-feat-desc">Deep learning algorithms predict conversion probability and route traffic to highest-performing segments automatically.</div>
            </div>

            <div class="ai-feat">
                <div class="ai-feat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div class="ai-feat-name">Real-Time Learning</div>
                <div class="ai-feat-desc">AI model updates every 60 seconds based on live conversion data, continuously improving targeting accuracy.</div>
            </div>

            <div class="ai-feat">
                <div class="ai-feat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <div class="ai-feat-name">Fraud Prevention AI</div>
                <div class="ai-feat-desc">Multi-layer neural network filters bots, proxies, and suspicious patterns with 99.7% accuracy before traffic reaches you.</div>
            </div>

            <div class="ai-feat">
                <div class="ai-feat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                </div>
                <div class="ai-feat-name">Predictive Scaling</div>
                <div class="ai-feat-desc">AI forecasts traffic demand patterns and pre-allocates resources to maintain consistent quality at any volume.</div>
            </div>
        </div>
    </div>
</section>

<footer class="foot-mini">
    <div class="foot-showcase">
        <img src="/assets/img/footer/showcase.jpg" alt="Advora platform" loading="lazy">
    </div>

    <div class="foot-mini-inner">
        <div class="foot-dev">
            <div>
                <div class="foot-dev-label">Built by</div>
                <div class="foot-dev-links">
                    <a href="https://t.me/hexmanual" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.008-1.252-.241-1.865-.44-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        @hexmanual
                    </a>
                    <span class="foot-dev-sep">&middot;</span>
                    <a href="https://wa.me/12506505872" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<script>
(function(){
    const viz = document.getElementById('trafficViz');
    if(!viz) return;
    
    const hours = 24;
    const data = Array.from({length:hours}, () => 40 + Math.random() * 60);
    
    data.forEach((val,i) => {
        const bar = document.createElement('div');
        bar.className = 'traffic-bar';
        bar.style.height = val + '%';
        bar.dataset.val = `${(val * 1.2).toFixed(0)}K`;
        bar.style.animationDelay = (i * 20) + 'ms';
        viz.appendChild(bar);
    });
    
    setInterval(() => {
        const bars = viz.querySelectorAll('.traffic-bar');
        bars.forEach(bar => {
            const newVal = 40 + Math.random() * 60;
            bar.style.height = newVal + '%';
            bar.dataset.val = `${(newVal * 1.2).toFixed(0)}K`;
        });
    }, 3000);
})();

(function(){
    const score = document.getElementById('aiScore');
    if(!score) return;
    
    score.innerHTML = `
        <div class="ai-score-circle">
            <svg viewBox="0 0 160 160">
                <circle class="ai-score-bg" cx="80" cy="80" r="70" stroke-dasharray="440" stroke-dashoffset="0"/>
                <circle class="ai-score-fill" cx="80" cy="80" r="70" stroke-dasharray="440" stroke-dashoffset="440"/>
            </svg>
            <div class="ai-score-text">
                <div class="ai-score-num">94</div>
                <div class="ai-score-label">AI Score</div>
            </div>
        </div>
    `;
})();

(function(){
    const chart = document.getElementById('qualityChart');
    if(!chart) return;
    
    const data = [
        {height: 92, color: 'var(--signal)'},
        {height: 88, color: 'var(--signal)'},
        {height: 95, color: 'var(--signal)'},
        {height: 90, color: 'var(--signal)'},
        {height: 85, color: 'var(--signal)'},
        {height: 78, color: 'var(--gold)'},
        {height: 82, color: 'var(--gold)'},
        {height: 30, color: 'var(--dim)'},
        {height: 25, color: 'var(--dim)'},
    ];
    
    data.forEach((item, i) => {
        const bar = document.createElement('div');
        bar.className = 'quality-bar';
        bar.style.background = `linear-gradient(180deg, ${item.color}, rgba(255,255,255,0.1))`;
        bar.style.height = '0%';
        setTimeout(() => {
            bar.style.height = item.height + '%';
        }, i * 80);
        chart.appendChild(bar);
    });
})();

(function(){
    const els = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if(e.isIntersecting){
                e.target.classList.add('in');
                io.unobserve(e.target);
            }
        });
    },{threshold:0.12,rootMargin:'0px 0px -60px 0px'});
    els.forEach(el => io.observe(el));
})();
</script>

</body>
</html>