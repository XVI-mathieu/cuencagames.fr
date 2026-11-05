/* ==========================================================================
   Commandes tactiles universelles pour les jeux en version navigateur.
   Ajouté automatiquement à chaque jeu par le site (voir app/Core/Tactile.php), jamais copié dans les fichiers des jeux.
   Sur téléphone / tablette, il affiche un joystick virtuel et des boutons qui envoient de VRAIS évènements clavier
   (flèches, ZQSD, Espace, Maj…) et, pour les jeux visés à la souris, transforme le toucher en évènements souris.
   Rien ne s'affiche sur ordinateur. La configuration vient de window.CG_TACTILE (profil du jeu).
   ========================================================================== */
(function () {
    'use strict';

    // region Conditions d'activation

    var cfg = window.CG_TACTILE || {};
    var forced = /[?&]tactile=1/.test(location.search);
    var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    if (window.CG && !forced) return;                         // les jeux « Cuenca Games » ont leurs propres commandes tactiles
    if (!(coarse || forced) || cfg.mode === 'none' && !cfg.landscape) return;

    // endregion

    // region Touches virtuelles : tableau des codes et envoi d'évènements clavier

    var KEYS = {
        ArrowUp: [38, 'ArrowUp'], ArrowDown: [40, 'ArrowDown'], ArrowLeft: [37, 'ArrowLeft'], ArrowRight: [39, 'ArrowRight'],
        KeyW: [87, 'w'], KeyA: [65, 'a'], KeyS: [83, 's'], KeyD: [68, 'd'], KeyZ: [90, 'z'], KeyQ: [81, 'q'], KeyE: [69, 'e'], KeyR: [82, 'r'],
        KeyX: [88, 'x'], KeyC: [67, 'c'], KeyF: [70, 'f'], KeyJ: [74, 'j'], KeyK: [75, 'k'], KeyP: [80, 'p'], KeyM: [77, 'm'], KeyV: [86, 'v'],
        Space: [32, ' '], Enter: [13, 'Enter'], Escape: [27, 'Escape'], Tab: [9, 'Tab'], ShiftLeft: [16, 'Shift'], ControlLeft: [17, 'Control'],
        Digit1: [49, '1'], Digit2: [50, '2'], Digit3: [51, '3'], Digit4: [52, '4'],
    };
    var down = {};                                                // touches actuellement « enfoncées » par nos commandes (compteur : stick + bouton peuvent partager une touche)

    /** Envoie un évènement clavier (keydown / keyup) à la page, comme si la touche était pressée. */
    function fire(type, code) {
        var k = KEYS[code]; if (!k) return;
        var init = { key: k[1], code: code, keyCode: k[0], which: k[0], bubbles: true, cancelable: true, shiftKey: code === 'ShiftLeft', ctrlKey: code === 'ControlLeft' };
        var ev = new KeyboardEvent(type, init);
        try { Object.defineProperty(ev, 'keyCode', { get: function () { return k[0]; } }); Object.defineProperty(ev, 'which', { get: function () { return k[0]; } }); } catch (e) { /* propriété non modifiable */ }
        (document.body || document).dispatchEvent(ev);
    }
    /** Presse une touche virtuelle (une seule pression réelle tant qu'une commande la maintient). */
    function press(code) { down[code] = (down[code] || 0) + 1; if (down[code] === 1) fire('keydown', code); }
    /** Relâche une touche virtuelle. */
    function release(code) { if (!down[code]) return; down[code]--; if (down[code] === 0) fire('keyup', code); }

    // endregion

    // region Interface : styles, conteneur, joystick, boutons

    var css = '#tc-root{position:fixed;inset:0;z-index:2147483000;pointer-events:none;font-family:system-ui,sans-serif;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none;touch-action:none}' +
        '#tc-root *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}' +
        '.tc-stick{position:absolute;left:max(14px,env(safe-area-inset-left));bottom:max(18px,env(safe-area-inset-bottom));width:140px;height:140px;border-radius:50%;pointer-events:auto;touch-action:none;background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.35);backdrop-filter:blur(3px)}' +
        '.tc-knob{position:absolute;left:50%;top:50%;width:62px;height:62px;margin:-31px 0 0 -31px;border-radius:50%;background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.85),rgba(255,255,255,.35));box-shadow:0 4px 12px rgba(0,0,0,.4)}' +
        '.tc-btns{position:absolute;right:max(14px,env(safe-area-inset-right));bottom:max(18px,env(safe-area-inset-bottom));display:flex;flex-wrap:wrap-reverse;justify-content:flex-end;gap:12px;max-width:230px;pointer-events:none}' +
        '.tc-btn{pointer-events:auto;touch-action:none;width:64px;height:64px;border-radius:50%;border:2px solid rgba(255,255,255,.4);background:rgba(255,255,255,.14);color:#fff;font:800 15px system-ui,sans-serif;display:grid;place-items:center;backdrop-filter:blur(3px);text-shadow:0 1px 3px #000}' +
        '.tc-btn.big{width:84px;height:84px;font-size:18px}.tc-btn.on{background:rgba(0,229,255,.55);border-color:#fff}' +
        '.tc-top{position:absolute;top:max(8px,env(safe-area-inset-top));right:max(8px,env(safe-area-inset-right));display:flex;gap:8px;pointer-events:auto}' +
        '.tc-top button{width:42px;height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.35);background:rgba(0,0,0,.45);color:#fff;font-size:18px}' +
        '.tc-hint{position:absolute;inset:0;display:grid;place-items:center;pointer-events:auto;background:rgba(0,0,0,.82);color:#fff;text-align:center;padding:24px;font-size:18px}' +
        '.tc-hint b{display:block;font-size:56px;margin-bottom:10px}.tc-hint small{display:block;margin-top:14px;opacity:.7}' +
        '.tc-hidden .tc-stick,.tc-hidden .tc-btns{display:none}';

    var root, stickEl, knob;
    /** Construit les commandes à l'écran selon le profil du jeu. */
    function build() {
        var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
        root = document.createElement('div'); root.id = 'tc-root'; document.body.appendChild(root);
        if (cfg.mode !== 'none') { buildStick(); buildButtons(); buildTop(); }
        if (cfg.landscape) rotationHint();
    }
    /** Joystick : la direction du doigt devient des pressions de flèches et de ZQSD. */
    function buildStick() {
        stickEl = document.createElement('div'); stickEl.className = 'tc-stick'; knob = document.createElement('div'); knob.className = 'tc-knob'; stickEl.appendChild(knob); root.appendChild(stickEl);
        var active = null, held = {}, mode = cfg.stick || '4';
        function set(dir, on) {
            if (!!held[dir] === on) return; held[dir] = on;
            var codes = { up: ['ArrowUp', 'KeyW'], down: ['ArrowDown', 'KeyS'], left: ['ArrowLeft', 'KeyA'], right: ['ArrowRight', 'KeyD'] }[dir];
            codes.forEach(function (c) { on ? press(c) : release(c); });
        }
        function move(e) {
            var r = stickEl.getBoundingClientRect(), cx = r.left + r.width / 2, cy = r.top + r.height / 2, dx = e.clientX - cx, dy = e.clientY - cy, d = Math.hypot(dx, dy), max = r.width / 2 - 8;
            if (d > max) { dx = dx / d * max; dy = dy / d * max; }
            knob.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
            var nx = dx / max, ny = dy / max;
            set('left', nx < -0.35); set('right', nx > 0.35);
            set('up', mode !== 'h' && ny < -0.35); set('down', mode !== 'h' && ny > 0.35);
        }
        function end(e) { if (active !== e.pointerId) return; active = null; knob.style.transform = ''; ['left', 'right', 'up', 'down'].forEach(function (d) { set(d, false); }); }
        stickEl.addEventListener('pointerdown', function (e) { active = e.pointerId; stickEl.setPointerCapture(e.pointerId); move(e); e.preventDefault(); });
        stickEl.addEventListener('pointermove', function (e) { if (active === e.pointerId) move(e); });
        stickEl.addEventListener('pointerup', end); stickEl.addEventListener('pointercancel', end);
    }
    /** Boutons d'action : chacun maintient une ou plusieurs touches tant que le doigt reste dessus. */
    function buildButtons() {
        var box = document.createElement('div'); box.className = 'tc-btns'; root.appendChild(box);
        (cfg.buttons || [['A', ['Space']], ['B', ['KeyE']], ['C', ['ShiftLeft']]]).forEach(function (b) {
            var el = document.createElement('div'); el.className = 'tc-btn' + (b[2] ? ' big' : ''); el.textContent = b[0]; box.appendChild(el);
            var pid = null;
            el.addEventListener('pointerdown', function (e) { pid = e.pointerId; el.setPointerCapture(pid); el.classList.add('on'); b[1].forEach(press); e.preventDefault(); });
            var up = function (e) { if (pid !== e.pointerId) return; pid = null; el.classList.remove('on'); b[1].forEach(release); };
            el.addEventListener('pointerup', up); el.addEventListener('pointercancel', up);
        });
    }
    /** Petits boutons du haut : pause, masquer les commandes, plein écran. */
    function buildTop() {
        var top = document.createElement('div'); top.className = 'tc-top'; root.appendChild(top);
        var add = function (label, fn) { var b = document.createElement('button'); b.textContent = label; b.addEventListener('click', fn); top.appendChild(b); };
        if (cfg.pause !== false) add('⏸', function () { ['Escape'].forEach(function (c) { fire('keydown', c); setTimeout(function () { fire('keyup', c); }, 60); }); });
        add('👁', function () { root.classList.toggle('tc-hidden'); });
        add('⛶', function () { var d = document.documentElement; if (document.fullscreenElement) document.exitFullscreen(); else if (d.requestFullscreen) d.requestFullscreen().catch(function () {}); });
    }
    /** Conseil d'orientation : certains jeux se jouent mieux en paysage. */
    function rotationHint() {
        var el = document.createElement('div'); el.className = 'tc-hint'; el.innerHTML = '<div><b>↻</b>Tournez votre téléphone en <strong>mode paysage</strong> pour mieux jouer.<small>Touchez l\'écran pour continuer quand même.</small></div>';
        el.addEventListener('click', function () { el.remove(); dismissed = true; });
        var dismissed = false;
        function check() { var portrait = innerHeight > innerWidth * 1.1; if (portrait && !dismissed && !el.parentNode) root.appendChild(el); else if (!portrait && el.parentNode) el.remove(); }
        addEventListener('resize', check); addEventListener('orientationchange', check); check();
    }

    // endregion

    // region Toucher → souris (jeux visés à la souris : canvas, tir, orientation vers le curseur)

    function mouseBridge() {
        var activeId = null, sx = 0, sy = 0, moved = false, target = null;
        var interactive = 'button,a,input,select,textarea,label,[role=button],.tc-btn,.tc-stick,.tc-top,.tc-hint';
        function send(type, t, x, y, buttons) {
            var el = document.elementFromPoint(x, y) || document.body;
            var ev = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y, screenX: x, screenY: y, button: 0, buttons: buttons, view: window });
            el.dispatchEvent(ev); return el;
        }
        document.addEventListener('touchstart', function (e) {
            if (activeId !== null) return; var t = e.changedTouches[0];
            if (t.target.closest && t.target.closest(interactive)) return;           // boutons, menus HTML : on laisse le navigateur faire
            activeId = t.identifier; sx = t.clientX; sy = t.clientY; moved = false;
            send('mousemove', t, t.clientX, t.clientY, 0); target = send('mousedown', t, t.clientX, t.clientY, 1); e.preventDefault();
        }, { passive: false, capture: true });
        document.addEventListener('touchmove', function (e) {
            for (var i = 0; i < e.changedTouches.length; i++) { var t = e.changedTouches[i]; if (t.identifier !== activeId) continue; if (Math.hypot(t.clientX - sx, t.clientY - sy) > 10) moved = true; send('mousemove', t, t.clientX, t.clientY, 1); e.preventDefault(); }
        }, { passive: false, capture: true });
        function end(e) {
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i]; if (t.identifier !== activeId) continue; activeId = null;
                send('mouseup', t, t.clientX, t.clientY, 0); if (!moved) send('click', t, t.clientX, t.clientY, 0); e.preventDefault();
            }
        }
        document.addEventListener('touchend', end, { passive: false, capture: true }); document.addEventListener('touchcancel', end, { passive: false, capture: true });
    }

    // endregion

    // region Démarrage

    function start() {
        build();
        if (cfg.mouse) mouseBridge();
        if (cfg.lock !== false) {                                                     // évite le défilement et le zoom par double-toucher pendant le jeu
            var s = document.createElement('style'); s.textContent = 'html,body{overscroll-behavior:none}'; document.head.appendChild(s);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

    // endregion
})();
