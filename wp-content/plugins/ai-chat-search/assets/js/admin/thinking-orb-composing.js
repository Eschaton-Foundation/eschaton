/*!
 * Composing orb adapted from thinking-orbs 0.1.1.
 * Copyright (c) 2026 Jakub Antalik
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

(function() {
    'use strict';

    function fibonacciDirection(index, count) {
        var golden = Math.PI * (3 - Math.sqrt(5));
        var y = 1 - (2 * (index + 0.5)) / count;
        var radius = Math.sqrt(1 - y * y);
        var angle = index * golden;

        return [radius * Math.cos(angle), y, radius * Math.sin(angle)];
    }

    function makeProjection(yaw, tilt, centerX, centerY) {
        var sinTilt = Math.sin(tilt);
        var cosTilt = Math.cos(tilt);
        var sinYaw = Math.sin(yaw);
        var cosYaw = Math.cos(yaw);

        return function(x, y, z) {
            var rotatedX = x * cosYaw + z * sinYaw;
            var rotatedZ = -x * sinYaw + z * cosYaw;
            var rotatedY = y * cosTilt - rotatedZ * sinTilt;
            var depth = y * sinTilt + rotatedZ * cosTilt;

            return [centerX + rotatedX, centerY - rotatedY, depth];
        };
    }

    function paintDots(context, dots) {
        dots.sort(function(a, b) {
            return a.z - b.z;
        });

        dots.forEach(function(dot) {
            var ink = Math.min(1, Math.max(0, dot.white));
            var gray = Math.round(ink * 255);

            context.fillStyle = 'rgba(' + gray + ',' + gray + ',' + gray + ',' + dot.alpha + ')';
            context.beginPath();
            context.arc(dot.x, dot.y, Math.max(0.3, dot.radius), 0, Math.PI * 2);
            context.fill();
        });
    }

    function drawComposingOrb(context, size, time) {
        var center = size / 2;
        var orbRadius = center * 0.78;
        var project = makeProjection(0, 0.3, center, center);
        var radiusScale = Math.pow(size / 300, 0.6);
        var dots = [];
        var ghostCount = 38;
        var laneCount = 12;
        var segmentCount = 44;
        var lane;
        var segment;

        for (var i = 0; i < ghostCount; i++) {
            var direction = fibonacciDirection(i, ghostCount);
            var ghostPoint = project(
                direction[0] * orbRadius,
                direction[1] * orbRadius,
                direction[2] * orbRadius
            );
            var ghostDepth = (ghostPoint[2] / orbRadius + 1) / 2;

            dots.push({
                x: ghostPoint[0],
                y: ghostPoint[1],
                z: ghostPoint[2],
                radius: 0.8 * radiusScale,
                white: 0.78,
                alpha: 0.1 + 0.22 * ghostDepth
            });
        }

        var tilt = 0.55;
        var unitX = 1;
        var unitZ = 0;
        var vectorX = -unitZ * Math.sin(tilt);
        var vectorY = Math.cos(tilt);
        var vectorZ = unitX * Math.sin(tilt);
        var normalX = -unitZ * vectorY;
        var normalY = unitZ * vectorX - unitX * vectorZ;
        var normalZ = unitX * vectorY;

        for (lane = 0; lane < laneCount; lane++) {
            var laneOffset = (lane - (laneCount - 1) / 2) * 0.075;
            var edge = Math.abs(lane - (laneCount - 1) / 2) / ((laneCount - 1) / 2);

            for (segment = 0; segment < segmentCount; segment++) {
                var angle = (segment / segmentCount) * 2 * Math.PI;
                var wobble =
                    0.16 * Math.sin(angle * 3 - time * 1.7 + lane * 0.22) +
                    0.07 * Math.sin(angle * 5 + time * 1.1);
                var offset = laneOffset + wobble;
                var x = unitX * Math.cos(angle) + vectorX * Math.sin(angle) + normalX * offset;
                var y = vectorY * Math.sin(angle) + normalY * offset;
                var z = unitZ * Math.cos(angle) + vectorZ * Math.sin(angle) + normalZ * offset;
                var length = Math.sqrt(x * x + y * y + z * z);
                var point = project(
                    (x / length) * orbRadius,
                    (y / length) * orbRadius,
                    (z / length) * orbRadius
                );
                var depth = (point[2] / orbRadius + 1) / 2;

                dots.push({
                    x: point[0],
                    y: point[1],
                    z: point[2],
                    radius: (0.935 + 1.445 * depth) * (1 - 0.25 * edge) * radiusScale,
                    white: 0.52 - 0.44 * depth + 0.18 * edge,
                    alpha: 0.4 + 0.6 * depth
                });
            }
        }

        paintDots(context, dots);
    }

    function initializeOrb(canvas) {
        var context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        var size = Number(canvas.dataset.size) || 70;
        var speed = Number(canvas.dataset.speed) || 1;
        var deviceScale = Math.min(2, window.devicePixelRatio || 1);
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var animationFrame = 0;
        var running = false;
        var visible = true;

        canvas.width = Math.round(size * deviceScale);
        canvas.height = Math.round(size * deviceScale);
        canvas.style.width = size + 'px';
        canvas.style.height = size + 'px';

        function draw(time) {
            context.setTransform(deviceScale, 0, 0, deviceScale, 0, 0);
            context.clearRect(0, 0, size, size);
            drawComposingOrb(context, size, time);
        }

        function loop() {
            draw((window.performance.now() / 1000) * 2.34 * speed);

            if (running) {
                animationFrame = window.requestAnimationFrame(loop);
            }
        }

        function start() {
            if (running || reducedMotion) {
                return;
            }

            running = true;
            animationFrame = window.requestAnimationFrame(loop);
        }

        function stop() {
            running = false;
            window.cancelAnimationFrame(animationFrame);
        }

        draw(reducedMotion ? 0.6 : (window.performance.now() / 1000) * 2.34 * speed);

        var observer = typeof window.IntersectionObserver !== 'undefined'
            ? new window.IntersectionObserver(function(entries) {
                visible = entries[0].isIntersecting;

                if (visible && document.visibilityState !== 'hidden') {
                    start();
                } else {
                    stop();
                }
            })
            : null;

        if (observer) {
            observer.observe(canvas);
        } else {
            start();
        }

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                stop();
            } else if (visible) {
                start();
            }
        });
    }

    function initializeOrbs() {
        document.querySelectorAll('.airs-composing-orb').forEach(initializeOrb);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeOrbs);
    } else {
        initializeOrbs();
    }
})();
