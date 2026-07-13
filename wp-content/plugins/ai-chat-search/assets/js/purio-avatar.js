/**
 * PurioChat animated avatar helper.
 */
(function () {
    'use strict';

    var STYLES = ['flare', 'nova'];
    var SPEEDS = ['slow', 'normal', 'fast'];
    var HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

    function hexToHsl(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        var r = parseInt(hex.slice(0, 2), 16) / 255;
        var g = parseInt(hex.slice(2, 4), 16) / 255;
        var b = parseInt(hex.slice(4, 6), 16) / 255;
        var max = Math.max(r, g, b);
        var min = Math.min(r, g, b);
        var l = (max + min) / 2;
        var h = 0;
        var s = 0;
        var d = max - min;

        if (d !== 0) {
            s = d / (1 - Math.abs(2 * l - 1));
            if (max === r) h = ((g - b) / d) % 6;
            else if (max === g) h = (b - r) / d + 2;
            else h = (r - g) / d + 4;
            h *= 60;
            if (h < 0) h += 360;
        }

        return { h: h, s: s * 100, l: l * 100 };
    }

    function hslToHex(h, s, l) {
        h = ((h % 360) + 360) % 360;
        s = Math.max(0, Math.min(100, s)) / 100;
        l = Math.max(0, Math.min(100, l)) / 100;
        var c = (1 - Math.abs(2 * l - 1)) * s;
        var x = c * (1 - Math.abs(((h / 60) % 2) - 1));
        var m = l - c / 2;
        var r = 0;
        var g = 0;
        var b = 0;

        if (h < 60) { r = c; g = x; }
        else if (h < 120) { r = x; g = c; }
        else if (h < 180) { g = c; b = x; }
        else if (h < 240) { g = x; b = c; }
        else if (h < 300) { r = x; b = c; }
        else { r = c; b = x; }

        function channel(value) {
            return ('0' + Math.round((value + m) * 255).toString(16)).slice(-2);
        }

        return '#' + channel(r) + channel(g) + channel(b);
    }

    function derive(hex, style) {
        var hsl = hexToHsl(hex);
        if (style === 'flare') {
            if (hsl.s < 12) {
                return {
                    a: hslToHex(0, 0, hsl.l),
                    b: hslToHex(0, 0, Math.min(90, hsl.l + 22)),
                    c: hslToHex(0, 0, Math.min(98, hsl.l + 58)),
                    d: hslToHex(0, 0, Math.max(0, hsl.l - 22))
                };
            }

            return {
                a: hslToHex(hsl.h, hsl.s, hsl.l),
                b: hslToHex(hsl.h + 8, Math.min(100, Math.max(70, hsl.s * 1.15)), Math.min(85, hsl.l + 22)),
                c: hslToHex(hsl.h - 4, Math.min(100, Math.max(65, hsl.s * 1.05)), Math.min(96, hsl.l + 56)),
                d: hslToHex(hsl.h - 6, Math.min(100, hsl.s * 1.12), Math.max(0, hsl.l - 22))
            };
        }

        if (hsl.s < 12) {
            var neutralLightness = Math.max(6, Math.min(88, hsl.l));
            return {
                a: hslToHex(0, 0, neutralLightness),
                b: hslToHex(0, 0, Math.min(94, neutralLightness + 22)),
                c: hslToHex(0, 0, Math.min(99, Math.max(92, neutralLightness + 40))),
                d: hslToHex(0, 0, Math.max(0, neutralLightness - 28))
            };
        }

        var s = Math.min(100, Math.max(hsl.s, 45));
        var l = Math.max(6, Math.min(70, hsl.l));

        return {
            a: hslToHex(hsl.h, s, l),
            b: hslToHex(hsl.h + 32, s * 0.88, Math.min(85, l + 19)),
            c: hslToHex(hsl.h + 8, s, 94),
            d: hslToHex(hsl.h - 14, Math.min(100, s * 1.1), Math.max(0, l - 27))
        };
    }

    function deriveNovaEffects(hex, style) {
        if (style !== 'nova') return null;

        var lightness = hexToHsl(hex).l;
        var ratio = Math.min(1, lightness / 45);
        var brightnessRatio = Math.pow(ratio, 2);
        var glowRatio = Math.pow(ratio, 1.8);

        return {
            brightness: (1.08 + (0.72 * brightnessRatio)).toFixed(2),
            glow: (0.9 * glowRatio).toFixed(2)
        };
    }

    function addFrontendPaletteStyle() {
        var config = window.purioAvatarFrontendConfig;
        if (!config || !HEX.test(config.color || '') || !document.head) return;

        var colors = derive(config.color, config.style);
        var effects = deriveNovaEffects(config.color, config.style);
        var style = document.createElement('style');
        style.id = 'purio-avatar-frontend-palette';
        style.textContent = '#listeo-floating-chat-button .listeo-floating-animated-avatar{' +
            '--pcha-a:' + colors.a + ';' +
            '--pcha-b:' + colors.b + ';' +
            '--pcha-c:' + colors.c + ';' +
            '--pcha-d:' + colors.d + ';' +
            (effects ? '--pcha-nova-peak-brightness:' + effects.brightness + ';--pcha-nova-peak-glow:' + effects.glow + ';' : '') +
            '}';
        document.head.appendChild(style);
    }

    function normalize(options) {
        options = options || {};
        var color = typeof options.color === 'string' && HEX.test(options.color.trim())
            ? options.color.trim()
            : null;
        var style = STYLES.indexOf(options.style) !== -1 ? options.style : STYLES[0];

        return {
            style: style,
            speed: SPEEDS.indexOf(options.speed) !== -1 ? options.speed : 'normal',
            size: parseInt(options.size, 10) > 0 ? parseInt(options.size, 10) : 48,
            colors: color ? derive(color, style) : null,
            effects: color ? deriveNovaEffects(color, style) : null
        };
    }

    function markup(options) {
        var normalized = normalize(options);
        var style = '--pcha-size:' + normalized.size + 'px';

        if (normalized.colors) {
            ['a', 'b', 'c', 'd'].forEach(function (key) {
                style += ';--pcha-' + key + ':' + normalized.colors[key];
            });
        }
        if (normalized.effects) {
            style += ';--pcha-nova-peak-brightness:' + normalized.effects.brightness;
            style += ';--pcha-nova-peak-glow:' + normalized.effects.glow;
        }

        return '<span class="pcha-avatar pcha-' + normalized.style + ' pcha-speed-' + normalized.speed + '" style="' + style + '" role="img" aria-label="Assistant avatar">' +
            '<span class="pcha-orb"><span class="pcha-scene">' +
            '<span class="pcha-blob pcha-b1"></span><span class="pcha-blob pcha-b2"></span><span class="pcha-blob pcha-b3"></span>' +
            '</span><span class="pcha-grain"></span></span></span>';
    }

    function create(options) {
        var template = document.createElement('div');
        template.innerHTML = markup(options);
        return template.firstChild;
    }

    function applyColor(element, color) {
        if (!element || !HEX.test(color || '')) return;
        var style = element.classList.contains('pcha-flare') ? 'flare' : 'nova';
        var colors = derive(color, style);
        ['a', 'b', 'c', 'd'].forEach(function (key) {
            element.style.setProperty('--pcha-' + key, colors[key]);
        });
        var effects = deriveNovaEffects(color, style);
        if (effects) {
            element.style.setProperty('--pcha-nova-peak-brightness', effects.brightness);
            element.style.setProperty('--pcha-nova-peak-glow', effects.glow);
        }
    }

    function hydrate(root) {
        var scope = root || document;
        var avatars = scope.querySelectorAll('.pcha-avatar[data-pcha-color]');
        Array.prototype.forEach.call(avatars, function (avatar) {
            applyColor(avatar, avatar.getAttribute('data-pcha-color'));
        });
    }

    function syncFloatingButtonState() {
        var button = document.getElementById('listeo-floating-chat-button');
        var popup = document.getElementById('listeo-floating-chat-popup');
        if (!button || !popup || !button.classList.contains('has-animated-avatar')) return;

        var isOpen = popup.style.display === 'block' && popup.style.opacity !== '0';
        button.classList.toggle('is-chat-open', isOpen);
    }

    function watchFloatingButtonState() {
        var button = document.getElementById('listeo-floating-chat-button');
        var popup = document.getElementById('listeo-floating-chat-popup');
        if (!button || !popup || !button.classList.contains('has-animated-avatar')) return;

        syncFloatingButtonState();

        if (typeof MutationObserver !== 'undefined') {
            new MutationObserver(syncFloatingButtonState).observe(popup, {
                attributes: true,
                attributeFilter: ['style']
            });
        }

        button.addEventListener('click', function() {
            setTimeout(syncFloatingButtonState, 0);
        });
    }

    document.addEventListener('visibilitychange', function () {
        document.documentElement.classList.toggle('pcha-tab-hidden', document.hidden);
    });

    addFrontendPaletteStyle();

    document.addEventListener('listeo-floating-chat-opened', function () {
        var button = document.getElementById('listeo-floating-chat-button');
        if (button && button.classList.contains('has-animated-avatar')) {
            button.classList.add('is-chat-open');
        }
    });

    document.addEventListener('listeo-floating-chat-closed', function () {
        var button = document.getElementById('listeo-floating-chat-button');
        if (button) button.classList.remove('is-chat-open');
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            hydrate(document);
            watchFloatingButtonState();
        });
    } else {
        hydrate(document);
        watchFloatingButtonState();
    }

    window.PurioAvatar = {
        STYLES: STYLES,
        SPEEDS: SPEEDS,
        derive: derive,
        markup: markup,
        create: create,
        applyColor: applyColor,
        hydrate: hydrate
    };
})();
