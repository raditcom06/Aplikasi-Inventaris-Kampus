/* =========================================================
   Intro setelah login - gaya editorial: hitam pekat, tipografi
   raksasa berbutir, gema huruf outline, bintang dan tanda silang.
   Ada adegan 3D (Three.js) monokrom yang tipis di belakang teks.
   Gulir ke bawah = teks membesar, gema melebar, lalu layar tembus
   ke tampilan utama. Tanpa Three.js / WebGL, intro tetap tampil.
   ========================================================= */
(function () {
    'use strict';

    var wrap = document.getElementById('intro');
    if (!wrap) return;
    var pin = wrap.querySelector('.intro-pin');
    var host = wrap.querySelector('.intro-canvas');
    var body = document.body;
    var kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var p = 0, pHalus = 0;
    var mx = 0, my = 0, mxH = 0, myH = 0;
    var terlihat = true, jalan = false;
    var gambar3D = null;

    var clamp01 = function (v) { return Math.min(1, Math.max(0, v)); };
    var ease = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };

    /* ---------- Loop utama: halus-kan gerak, set variabel CSS, gambar 3D ---------- */
    var t0 = performance.now();
    var loop = function (now) {
        if (!terlihat) { jalan = false; return; }
        pHalus += (p - pHalus) * 0.08;
        mxH += (mx - mxH) * 0.06;
        myH += (my - myH) * 0.06;
        pin.style.setProperty('--ps', pHalus.toFixed(4));
        pin.style.setProperty('--mx', mxH.toFixed(3));
        pin.style.setProperty('--my', myH.toFixed(3));
        if (gambar3D) gambar3D((now - t0) / 1000, ease(pHalus));
        requestAnimationFrame(loop);
    };
    var mulaiLoop = function () {
        if (jalan) return;
        jalan = true;
        requestAnimationFrame(loop);
    };

    /* ---------- Progres gulir -> variabel CSS ---------- */
    var hitungScroll = function () {
        var r = wrap.getBoundingClientRect();
        var total = wrap.offsetHeight - window.innerHeight;
        p = total > 0 ? clamp01(-r.top / total) : 1;
        var k = clamp01((p - 0.78) / 0.22);
        pin.style.setProperty('--p', p.toFixed(4));
        pin.style.setProperty('--k', k.toFixed(4));

        var tampil = r.bottom > 0 && r.top < window.innerHeight;
        var baruTampil = tampil && !terlihat;
        terlihat = tampil;
        if (baruTampil) mulaiLoop();

        body.classList.toggle('intro-selesai', r.bottom < window.innerHeight * 0.55);
    };

    var menunggu = false;
    var saatGulir = function () {
        if (menunggu) return;
        menunggu = true;
        requestAnimationFrame(function () { menunggu = false; hitungScroll(); });
    };
    window.addEventListener('scroll', saatGulir, { passive: true });
    window.addEventListener('resize', saatGulir);
    window.addEventListener('pointermove', function (e) {
        mx = (e.clientX / window.innerWidth) * 2 - 1;
        my = (e.clientY / window.innerHeight) * 2 - 1;
    });

    /* ---------- Tombol ---------- */
    var keUtama = function () {
        var tujuan = wrap.getBoundingClientRect().bottom + window.pageYOffset;
        window.scrollTo({ top: tujuan, behavior: kurangiGerak ? 'auto' : 'smooth' });
    };
    var mulai = document.getElementById('introMulai');
    var lewati = document.getElementById('introLewati');
    if (mulai) mulai.addEventListener('click', keUtama);
    if (lewati) lewati.addEventListener('click', keUtama);

    /* ---------- Adegan 3D monokrom ---------- */
    var bangun3D = function () {
        var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.outputEncoding = THREE.sRGBEncoding;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.1;
        host.appendChild(renderer.domElement);

        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);

        // Pantulan studio: satu softbox hangat di kanan atas, sisanya putih redup
        var envScene = new THREE.Scene();
        envScene.background = new THREE.Color(0x040404);
        var softbox = function (w, h, x, y, z, warna, daya) {
            var m = new THREE.Mesh(
                new THREE.PlaneGeometry(w, h),
                new THREE.MeshBasicMaterial({ color: new THREE.Color(warna).multiplyScalar(daya), side: THREE.DoubleSide })
            );
            m.position.set(x, y, z);
            m.lookAt(0, 0, 0);
            envScene.add(m);
        };
        softbox(8, 5, 8, 8, 3, 0xffe2b8, 9);
        softbox(4, 10, -9, 2, 3, 0xffffff, 3);
        softbox(4, 10, 9, 0, -5, 0xcfd8ff, 2.5);
        softbox(16, 3, 0, -1, -11, 0xffffff, 2);
        var pmrem = new THREE.PMREMGenerator(renderer);
        scene.environment = pmrem.fromScene(envScene, 0.03).texture;
        pmrem.dispose();

        scene.add(new THREE.AmbientLight(0x151515, 0.5));
        var kunci = new THREE.DirectionalLight(0xffe0b0, 1.2);
        kunci.position.set(7, 8, 6);
        scene.add(kunci);
        var tepi = new THREE.DirectionalLight(0xffffff, 0.5);
        tepi.position.set(-8, 3, -6);
        scene.add(tepi);

        var kaca = new THREE.MeshPhysicalMaterial({ color: 0x080808, metalness: 0.55, roughness: 0.14, clearcoat: 1, clearcoatRoughness: 0.08 });
        var abu = new THREE.MeshPhysicalMaterial({ color: 0x1b1b1b, metalness: 0.5, roughness: 0.3, clearcoat: 0.5, clearcoatRoughness: 0.2 });
        var krom = new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 1, roughness: 0.06 });

        var kotakBulat = function (w, h, d, r) {
            var b = Math.min(r * 0.7, d * 0.25, w * 0.25, h * 0.25);
            var iw = w - 2 * b, ih = h - 2 * b;
            var ir = Math.min(Math.max(r - b, 0.001), iw / 2 - 0.001, ih / 2 - 0.001);
            var x = -iw / 2, y = -ih / 2;
            var s = new THREE.Shape();
            s.moveTo(x + ir, y);
            s.lineTo(x + iw - ir, y);
            s.absarc(x + iw - ir, y + ir, ir, -Math.PI / 2, 0, false);
            s.lineTo(x + iw, y + ih - ir);
            s.absarc(x + iw - ir, y + ih - ir, ir, 0, Math.PI / 2, false);
            s.lineTo(x + ir, y + ih);
            s.absarc(x + ir, y + ih - ir, ir, Math.PI / 2, Math.PI, false);
            s.lineTo(x, y + ir);
            s.absarc(x + ir, y + ir, ir, Math.PI, Math.PI * 1.5, false);
            var geo = new THREE.ExtrudeGeometry(s, {
                depth: d - 2 * b, bevelEnabled: true, bevelThickness: b, bevelSize: b, bevelSegments: 8, curveSegments: 14
            });
            geo.translate(0, 0, -(d - 2 * b) / 2);
            return geo;
        };

        var grup = new THREE.Group();
        scene.add(grup);
        var daftar = [];
        var acak = (function () {
            var s = 5;
            return function () { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; };
        })();
        var taruh = function (mesh, x, y, z, rx, ry, rz) {
            mesh.position.set(x, y, z);
            mesh.rotation.set(rx, ry, rz);
            mesh.userData = {
                x0: x, y0: y, z0: z, f: 0.3 + acak() * 0.4, ph: acak() * 6.28, amp: 0.12 + acak() * 0.15,
                sx: (acak() - 0.5) * 0.004, sy: (acak() - 0.5) * 0.006 + 0.002
            };
            grup.add(mesh);
            daftar.push(mesh);
        };

        // lempeng kaca gelap di pojok (seperti di referensi)
        taruh(new THREE.Mesh(kotakBulat(3.4, 0.4, 2.2, 0.12), kaca), 6.2, 3.1, -1, 0.5, -0.6, 0.4);
        taruh(new THREE.Mesh(kotakBulat(3.0, 0.35, 2.0, 0.12), kaca), -6.4, -3.0, -1.5, -0.4, 0.7, -0.3);
        // bola krom
        taruh(new THREE.Mesh(new THREE.SphereGeometry(0.6, 64, 48), krom), 4.3, -3.2, 1, 0, 0, 0);
        taruh(new THREE.Mesh(new THREE.SphereGeometry(0.38, 48, 32), krom), -4.6, 3.2, 0.5, 0, 0, 0);
        // cincin tipis
        taruh(new THREE.Mesh(new THREE.TorusGeometry(1.5, 0.025, 20, 160), krom), -6.8, 0.6, -2.5, 1.1, 0.4, 0);
        taruh(new THREE.Mesh(new THREE.TorusGeometry(1.0, 0.02, 20, 140), krom), 6.6, -0.4, -3, 0.5, 1.0, 0.2);
        // kotak kecil
        taruh(new THREE.Mesh(kotakBulat(1.0, 1.0, 1.0, 0.16), kaca), -2.6, -3.4, -2, 0.4, 0.6, 0.2);
        taruh(new THREE.Mesh(kotakBulat(0.8, 0.8, 0.8, 0.14), abu), 2.8, 3.5, -2.5, 0.3, -0.5, 0.3);

        var aspek = 1;
        var ukur = function () {
            var w = host.clientWidth || window.innerWidth;
            var h = host.clientHeight || window.innerHeight;
            renderer.setSize(w, h, false);
            aspek = w / h;
            camera.aspect = aspek;
            camera.updateProjectionMatrix();
        };
        ukur();
        window.addEventListener('resize', ukur);

        gambar3D = function (t, e) {
            var gerak = kurangiGerak ? 0 : 1;
            grup.rotation.y = mxH * 0.15;
            grup.rotation.x = myH * 0.08;
            var sebar = 1 + e * 0.9;
            for (var q = 0; q < daftar.length; q++) {
                var o = daftar[q], d = o.userData;
                o.position.x = d.x0 * sebar;
                o.position.y = d.y0 * sebar + Math.sin(t * d.f + d.ph) * d.amp * gerak;
                o.position.z = d.z0 + e * 4.5;
                o.rotation.x += d.sx * gerak;
                o.rotation.y += d.sy * gerak;
            }
            var jarak = (16 - e * 4) * Math.max(1, 1.1 / aspek);
            camera.position.set(mxH * 0.5, myH * -0.3, jarak);
            camera.lookAt(0, 0, 0);
            renderer.render(scene, camera);
        };
    };

    var berhasil = false;
    if (typeof THREE !== 'undefined') {
        try { bangun3D(); berhasil = true; } catch (err) { berhasil = false; }
    }
    if (!berhasil) body.classList.add('intro-tanpa-3d');

    hitungScroll();
    mulaiLoop();
})();