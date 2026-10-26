/* =========================================================
   Inventaris Fasilitas Kampus - app.js
   Tiap fitur mengecek dulu elemennya ada atau tidak, jadi
   aman dipakai di semua halaman.
   ========================================================= */
(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var qsa = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
    var root = document.documentElement;
    var kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var bolehTilt = window.matchMedia('(hover: hover)').matches && !kurangiGerak;

    /* =====================================================
       1. TRANSISI ZOOM ANTAR HALAMAN
       ===================================================== */
    var sedangZoom = false;
    var lewatiSubmit = false;
    var WARNA = { lime: '#d6f25a', blue: '#b9a8f5', red: '#17181b', ink: '#17181b', amber: '#d6f25a' };

    setTimeout(function () { root.classList.remove('zoom-in'); }, 1100);
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            root.classList.remove('zoom-out', 'zoom-in');
            sedangZoom = false;
            try { sessionStorage.removeItem('zoomHalaman'); } catch (err) {}
        }
    });

    var warnaUntuk = function (el) {
        var z = el.closest ? el.closest('[data-zoom]') : null;
        if (z && WARNA[z.dataset.zoom]) return WARNA[z.dataset.zoom];
        var h = (el.getAttribute && (el.getAttribute('href') || el.getAttribute('action'))) || '';
        if (/laporkan|hapus/.test(h)) return WARNA.red;
        if (/edit|ruangan|register/.test(h)) return WARNA.blue;
        if (/logout/.test(h)) return WARNA.ink;
        return WARNA.lime;
    };

    var mulaiZoom = function (el, warna, lanjut) {
        if (sedangZoom) return;
        sedangZoom = true;
        var r = el.getBoundingClientRect();
        var x = r.left + r.width / 2, y = r.top + r.height / 2;
        var halaman = document.querySelector('.halaman');
        root.style.setProperty('--zx', x + 'px');
        root.style.setProperty('--zy', y + 'px');
        root.style.setProperty('--pc', warna);
        if (halaman) {
            var hr = halaman.getBoundingClientRect();
            halaman.style.setProperty('--ox', (x - hr.left) + 'px');
            halaman.style.setProperty('--oy', (y - hr.top) + 'px');
        }
        try { sessionStorage.setItem('zoomHalaman', JSON.stringify({ x: Math.round(x), y: Math.round(y), c: warna })); } catch (err) {}
        root.classList.add('zoom-out');
        setTimeout(lanjut, 520);
        // jaring pengaman kalau navigasi ternyata tidak terjadi
        setTimeout(function () { root.classList.remove('zoom-out'); sedangZoom = false; }, 7000);
    };

    document.addEventListener('click', function (e) {
        if (kurangiGerak || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-zoom')) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href) || a.origin !== location.origin) return;
        e.preventDefault();
        mulaiZoom(a, warnaUntuk(a), function () { location.href = a.href; });
    });

    document.addEventListener('submit', function (e) {
        if (lewatiSubmit) { lewatiSubmit = false; return; }
        var f = e.target;
        if (kurangiGerak || e.defaultPrevented || f.hasAttribute('data-no-zoom')) return;
        e.preventDefault();
        var pemicu = e.submitter || null;
        mulaiZoom(pemicu || f.querySelector('[type=submit]') || f, warnaUntuk(f), function () {
            lewatiSubmit = true;
            if (f.requestSubmit) f.requestSubmit(pemicu || undefined); else f.submit();
        });
    });

    /* =====================================================
       2. TOAST
       ===================================================== */
    var toast = $('toast');
    var toastTimer;
    var showToast = function (msg, tipe) {
        if (!toast) return;
        $('toastMsg').textContent = msg;
        toast.classList.toggle('error', tipe === 'error');
        toast.querySelector('i').textContent = tipe === 'error' ? '!' : '\u2713';
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 3400);
    };
    if (toast && toast.dataset.flash) {
        setTimeout(function () { showToast(toast.dataset.flash, toast.dataset.tipe); }, 900);
    }

    /* =====================================================
       3. TOMBOL: ripple, loading, magnetis
       ===================================================== */
    qsa('.btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var r = btn.getBoundingClientRect();
            var size = Math.max(r.width, r.height);
            var dot = document.createElement('span');
            dot.className = 'ripple';
            dot.style.width = dot.style.height = size + 'px';
            dot.style.left = (e.clientX - r.left - size / 2) + 'px';
            dot.style.top = (e.clientY - r.top - size / 2) + 'px';
            btn.appendChild(dot);
            setTimeout(function () { dot.remove(); }, 600);
        });
        var form = btn.closest('form');
        if (form) form.addEventListener('submit', function () { btn.classList.add('loading'); });
    });

    if (bolehTilt) {
        qsa('.btn, .aksi, .chip, .btn-tambah, .btn-ghost').forEach(function (el) {
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                var dx = (e.clientX - (r.left + r.width / 2)) / r.width;
                var dy = (e.clientY - (r.top + r.height / 2)) / r.height;
                el.style.translate = (dx * 9).toFixed(1) + 'px ' + (dy * 6).toFixed(1) + 'px';
            });
            el.addEventListener('pointerleave', function () { el.style.translate = ''; });
        });
    }

    /* =====================================================
       4. PASSWORD
       ===================================================== */
    qsa('[data-pw]').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = $(b.dataset.pw);
            var tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            b.classList.toggle('on', tampil);
        });
    });

    /* =====================================================
       5. ANGKA STATISTIK + BAR
       ===================================================== */
    var jalankanStat = function () {
        qsa('[data-count]').forEach(function (el) {
            var target = parseInt(el.dataset.count, 10) || 0;
            var durasi = 1100, mulai = null;
            var langkah = function (ts) {
                if (!mulai) mulai = ts;
                var p = Math.min((ts - mulai) / durasi, 1);
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(langkah);
            };
            requestAnimationFrame(langkah);
        });
        setTimeout(function () {
            qsa('[data-w]').forEach(function (s) { s.style.width = s.dataset.w + '%'; });
        }, 150);
    };
    var areaStat = document.querySelector('.dash');
    if (document.body.classList.contains('intro-aktif') && areaStat && 'IntersectionObserver' in window) {
        // ada intro: mulai menghitung saat pengunjung sampai di tampilan utama
        var pengamat = new IntersectionObserver(function (en) {
            if (en[0].isIntersecting) { pengamat.disconnect(); jalankanStat(); }
        }, { threshold: 0.3 });
        pengamat.observe(areaStat);
    } else {
        jalankanStat();
    }

    /* =====================================================
       6. DAFTAR: cari, filter, tampilan tabel/kartu
       ===================================================== */
    var wadah = $('inventaris');
    var induk = wadah || $('tbody');
    var cari = $('cari');
    var empty = $('empty');
    var aktif = 'all';

    if (induk) {
        var semuaItem = wadah ? qsa('tbody tr, .asset-card', wadah) : qsa('tr', induk);
        var barisTabel = semuaItem.filter(function (el) { return el.tagName === 'TR'; });

        // animasi masuk ulang, berurutan, untuk item yang terlihat
        var animasiUlang = function () {
            var kartu = wadah && wadah.dataset.view === 'kartu';
            var terlihat = semuaItem.filter(function (el) {
                var jenisKartu = el.classList.contains('asset-card');
                return (!wadah || jenisKartu === !!kartu) && !el.classList.contains('hide');
            });
            terlihat.forEach(function (el, i) {
                el.style.animation = 'none';
                void el.offsetWidth;
                el.style.animation = '';
                el.style.animationDelay = (Math.min(i, 10) * 0.05) + 's';
            });
        };

        var terapkan = function (animasi) {
            var q = cari ? cari.value.trim().toLowerCase() : '';
            semuaItem.forEach(function (el) {
                var cocokKondisi = aktif === 'all' || el.dataset.kondisi === aktif;
                var cocokCari = el.textContent.toLowerCase().indexOf(q) !== -1;
                el.classList.toggle('hide', !(cocokKondisi && cocokCari));
            });
            var tampil = barisTabel.filter(function (tr) { return !tr.classList.contains('hide'); }).length;
            if (barisTabel.length && empty) {
                empty.classList.toggle('show', tampil === 0);
                empty.querySelector('strong').textContent = 'Tidak ada aset yang cocok';
            }
            if (animasi) animasiUlang();
        };

        var setFilter = function (nilai) {
            aktif = nilai;
            qsa('.chip').forEach(function (c) { c.classList.toggle('active', c.dataset.filter === nilai); });
            qsa('[data-filter-klik]').forEach(function (s) { s.classList.toggle('aktif', nilai !== 'all' && s.dataset.filterKlik === nilai); });
            terapkan(true);
        };

        if (cari) cari.addEventListener('input', function () { terapkan(false); });
        var chips = $('chips');
        if (chips) chips.addEventListener('click', function (e) {
            var chip = e.target.closest('.chip');
            if (chip) setFilter(chip.dataset.filter);
        });

        // gelembung dan baris persentase = filter cepat
        qsa('[data-filter-klik]').forEach(function (s) {
            s.addEventListener('click', function () {
                var nilai = s.dataset.filterKlik;
                setFilter(aktif === nilai ? 'all' : nilai);
                var kartuDaftar = induk.closest('.card');
                if (kartuDaftar) kartuDaftar.scrollIntoView({ behavior: kurangiGerak ? 'auto' : 'smooth', block: 'start' });
            });
        });

        // klik kode untuk menyalin
        induk.addEventListener('click', function (e) {
            var code = e.target.closest('.code');
            if (!code || !navigator.clipboard) return;
            navigator.clipboard.writeText(code.textContent.trim()).then(function () {
                showToast('Kode ' + code.textContent.trim() + ' disalin');
            });
        });

        // pilihan tampilan: tabel <-> kartu 3D, dengan zoom
        var toggle = document.querySelector('.view-toggle');
        if (wadah && toggle) {
            var setView = function (v, animasi) {
                if (wadah.dataset.view === v) return;
                var ganti = function () {
                    wadah.dataset.view = v;
                    toggle.dataset.aktif = v;
                    qsa('.vt', toggle).forEach(function (b) { b.classList.toggle('active', b.dataset.view === v); });
                    try { localStorage.setItem('viewInventaris', v); } catch (err) {}
                    animasiUlang();
                };
                if (!animasi || kurangiGerak) { ganti(); return; }
                wadah.classList.add('zoom-keluar');
                setTimeout(function () {
                    ganti();
                    wadah.classList.remove('zoom-keluar');
                    wadah.classList.add('zoom-masuk');
                    setTimeout(function () { wadah.classList.remove('zoom-masuk'); }, 650);
                }, 260);
            };
            toggle.addEventListener('click', function (e) {
                var b = e.target.closest('.vt');
                if (b) setView(b.dataset.view, true);
            });
            try {
                var simpanan = localStorage.getItem('viewInventaris');
                if (simpanan === 'kartu') setView('kartu', false);
            } catch (err) {}
        }
    }

    /* =====================================================
       7. MODAL (hapus, tambah aset)
       ===================================================== */
    var bukaModal = function (m) {
        if (!m) return;
        m.classList.add('open');
        m.setAttribute('aria-hidden', 'false');
        var pertama = m.querySelector('input:not([type=hidden]), select, textarea');
        if (pertama && m.id !== 'modalHapus') setTimeout(function () { pertama.focus(); }, 350);
    };
    var tutupSemuaModal = function () {
        qsa('.modal.open').forEach(function (m) { m.classList.remove('open'); m.setAttribute('aria-hidden', 'true'); });
    };
    document.addEventListener('click', function (e) {
        var pembuka = e.target.closest('[data-buka]');
        if (pembuka) { bukaModal($(pembuka.dataset.buka)); return; }

        var tombol = e.target.closest('[data-hapus]');
        if (tombol && $('modalHapus')) {
            var teks = { aset: 'Aset', ruangan: 'Ruangan', laporan: 'Laporan' };
            var jenis = tombol.dataset.hapus;
            $('hapusJenis').value = jenis;
            $('hapusId').value = tombol.dataset.id;
            $('modalJudul').textContent = 'Hapus ' + teks[jenis].toLowerCase() + ' ini?';
            $('modalTeks').textContent = teks[jenis] + ' "' + tombol.dataset.nama + '" akan dihapus permanen' +
                (jenis === 'aset' ? ', termasuk riwayat laporan kerusakannya.' : '.');
            bukaModal($('modalHapus'));
            return;
        }

        var tutup = e.target.closest('[data-tutup]');
        if (tutup || (e.target.classList && e.target.classList.contains('modal'))) tutupSemuaModal();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupSemuaModal(); });
    if (/[?&]tambah=1/.test(location.search) && $('modalAset')) setTimeout(function () { bukaModal($('modalAset')); }, 700);

    /* ---------- Rentang grafik laporan (6 / 12 bulan) ---------- */
    var rentang = $('rentang');
    if (rentang) {
        var terapkanRentang = function () {
            var n = parseInt(rentang.value, 10);
            var kolom = qsa('.bln');
            kolom.forEach(function (k, i) { k.classList.toggle('sembunyi', i < kolom.length - n); });
        };
        rentang.addEventListener('change', terapkanRentang);
        terapkanRentang();
    }

    /* =====================================================
       8. MENU NAVBAR (HP)
       ===================================================== */
    var navToggle = $('navToggle');
    if (navToggle) {
        navToggle.addEventListener('click', function () {
            navToggle.classList.toggle('open');
            $('navLinks').classList.toggle('open');
        });
    }

    /* =====================================================
       9. FORM LAPORAN BANYAK BARANG (laporkan.php)
       ===================================================== */
    var daftar = $('daftarItem');
    if (daftar && window.LAPOR) {
        var daftarAset = window.LAPOR.aset;
        var peta = {};
        daftarAset.forEach(function (a) { peta[a.id] = a; });
        var MAKS = window.LAPOR.maks;
        var pilih = $('tambahPilih'), btnTambah = $('tambahBtn'), btnKirim = $('btnKirim');

        var buat = function (tag, cls, teksIsi) {
            var n = document.createElement(tag);
            if (cls) n.className = cls;
            if (teksIsi !== undefined) n.textContent = teksIsi;
            return n;
        };
        var kelasKondisi = function (k) { return k === 'Baik' ? 'baik' : (k === 'Rusak Ringan' ? 'ringan' : 'berat'); };

        var buatPilihan = function (id, nilai, cls, judul, ket, dicentang) {
            var w = buat('div', 'choice ' + cls);
            var inp = buat('input');
            inp.type = 'radio'; inp.name = 'item[' + id + '][kondisi]'; inp.id = 'k' + id + cls; inp.value = nilai;
            inp.checked = dicentang;
            var lab = buat('label'); lab.htmlFor = inp.id;
            var st = buat('strong'); st.appendChild(buat('i', 'dot')); st.appendChild(document.createTextNode(judul));
            lab.appendChild(st); lab.appendChild(buat('span', '', ket));
            w.appendChild(inp); w.appendChild(lab);
            return w;
        };

        var itemAktif = function () { return qsa('.lapor-item:not(.keluar)', daftar); };

        var buatItem = function (data) {
            var a = peta[data.id];
            var art = buat('article', 'lapor-item');
            art.dataset.id = a.id;

            var head = buat('div', 'li-head');
            var ikonEl = buat('span', 'li-ikon');
            ikonEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>';
            var info = buat('div', 'li-info');
            info.appendChild(buat('strong', '', a.nama_barang));
            var sub = buat('small');
            sub.appendChild(buat('span', 'code', a.kode_asset));
            sub.appendChild(document.createTextNode(a.nama_ruangan + ' (' + a.gedung + ')'));
            info.appendChild(sub);
            var badge = buat('span', 'badge ' + kelasKondisi(a.kondisi), 'Saat ini: ' + a.kondisi);
            var hapus = buat('button', 'li-hapus', '\u00d7');
            hapus.type = 'button';
            hapus.setAttribute('aria-label', 'Hapus dari laporan');
            hapus.addEventListener('click', function () {
                art.classList.add('keluar');
                setTimeout(function () { art.remove(); segarkan(); }, 250);
                segarkan();
            });
            head.appendChild(ikonEl); head.appendChild(info); head.appendChild(badge); head.appendChild(hapus);

            var pilihan = buat('div', 'choices');
            var berat = data.kondisi === 'Rusak Berat';
            pilihan.appendChild(buatPilihan(a.id, 'Rusak Ringan', 'r', 'Rusak ringan', 'Masih bisa dipakai, ada kendala kecil', !berat));
            pilihan.appendChild(buatPilihan(a.id, 'Rusak Berat', 'b', 'Rusak berat', 'Tidak bisa dipakai sama sekali', berat));

            var ta = buat('textarea');
            ta.name = 'item[' + a.id + '][deskripsi]';
            ta.rows = 3; ta.maxLength = 500; ta.required = true;
            ta.placeholder = 'Apa yang rusak? Contoh: lensa buram, kabel HDMI putus...';
            ta.value = data.deskripsi || '';

            art.appendChild(head); art.appendChild(pilihan); art.appendChild(ta);
            return art;
        };

        var isiPilihan = function () {
            var ada = {};
            itemAktif().forEach(function (x) { ada[x.dataset.id] = true; });
            pilih.innerHTML = '';
            var grup = {}, urut = [];
            daftarAset.forEach(function (a) {
                if (ada[a.id]) return;
                var kunci = a.nama_ruangan + ' (' + a.gedung + ')';
                if (!grup[kunci]) { grup[kunci] = document.createElement('optgroup'); grup[kunci].label = kunci; urut.push(kunci); }
                var o = document.createElement('option');
                o.value = a.id; o.textContent = a.nama_barang + ' - ' + a.kode_asset;
                grup[kunci].appendChild(o);
            });
            var penuh = itemAktif().length >= MAKS;
            if (!urut.length || penuh) {
                var o2 = document.createElement('option');
                o2.textContent = penuh ? 'Batas ' + MAKS + ' barang per laporan' : 'Semua barang sudah ditambahkan';
                pilih.appendChild(o2);
                pilih.disabled = true; btnTambah.disabled = true;
            } else {
                urut.forEach(function (k) { pilih.appendChild(grup[k]); });
                pilih.disabled = false; btnTambah.disabled = false;
            }
        };

        var segarkan = function () {
            var n = itemAktif().length;
            $('jumlahItem').textContent = n;
            $('kosongItem').classList.toggle('sembunyi', n > 0);
            btnKirim.disabled = n === 0;
            isiPilihan();
        };

        var tambahItem = function (data) {
            if (!peta[data.id] || itemAktif().length >= MAKS) return;
            var sudah = itemAktif().some(function (x) { return x.dataset.id === String(data.id); });
            if (sudah) return;
            daftar.appendChild(buatItem(data));
            segarkan();
        };

        btnTambah.addEventListener('click', function () {
            if (!pilih.value) return;
            tambahItem({ id: parseInt(pilih.value, 10), kondisi: 'Rusak Ringan', deskripsi: '' });
            var ta = daftar.querySelector('.lapor-item:last-child textarea');
            if (ta) ta.focus();
        });

        window.LAPOR.awal.forEach(tambahItem);
        segarkan();
    }

    /* =====================================================
       10. EFEK 3D: miring mengikuti kursor + kardus yang bisa diseret
       ===================================================== */
    if (bolehTilt) {
        qsa('.asset-card, .stat').forEach(function (el) {
            var maks = el.classList.contains('stat') ? 8 : 11;
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                var px = (e.clientX - r.left) / r.width;
                var py = (e.clientY - r.top) / r.height;
                el.style.setProperty('--ry', ((px - 0.5) * maks * 2).toFixed(2) + 'deg');
                el.style.setProperty('--rx', ((0.5 - py) * maks * 2).toFixed(2) + 'deg');
                el.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
                el.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            });
            el.addEventListener('pointerleave', function () {
                el.style.setProperty('--rx', '0deg');
                el.style.setProperty('--ry', '0deg');
            });
        });
    }

    var heroEl = document.querySelector('.hero');
    var scene = document.querySelector('.scene');
    var stack = document.querySelector('.stack');
    if (heroEl && scene && stack) {
        if (bolehTilt) {
            heroEl.addEventListener('pointermove', function (e) {
                var r = heroEl.getBoundingClientRect();
                scene.style.setProperty('--sx', (((e.clientX - r.left) / r.width - 0.5) * 2).toFixed(2));
                scene.style.setProperty('--sy', (((e.clientY - r.top) / r.height - 0.5) * 2).toFixed(2));
            });
            heroEl.addEventListener('pointerleave', function () {
                scene.style.setProperty('--sx', 0);
                scene.style.setProperty('--sy', 0);
            });
        }

        // putaran otomatis + seret dengan inersia
        var sudutY = 35, sudutX = -22, laju = 0.3, vY = 0;
        var diseret = false, lastX = 0, lastY = 0, jarak = 0, kubusAwal = null;
        var gambar = function () { stack.style.transform = 'rotateX(' + sudutX.toFixed(2) + 'deg) rotateY(' + sudutY.toFixed(2) + 'deg)'; };

        var putar = function () {
            if (!diseret) {
                sudutY += laju + vY;
                vY *= 0.94;
                if (Math.abs(vY) < 0.01) vY = 0;
                sudutX += (-22 - sudutX) * 0.04;
            }
            gambar();
            requestAnimationFrame(putar);
        };
        if (kurangiGerak) gambar(); else requestAnimationFrame(putar);

        scene.addEventListener('pointerdown', function (e) {
            diseret = true; jarak = 0; vY = 0;
            lastX = e.clientX; lastY = e.clientY;
            kubusAwal = e.target.closest ? e.target.closest('.cube') : null;
            scene.classList.add('drag');
            try { scene.setPointerCapture(e.pointerId); } catch (err) {}
        });
        scene.addEventListener('pointermove', function (e) {
            if (!diseret) return;
            var dx = e.clientX - lastX, dy = e.clientY - lastY;
            lastX = e.clientX; lastY = e.clientY;
            jarak += Math.abs(dx) + Math.abs(dy);
            sudutY += dx * 0.6;
            sudutX = Math.max(-65, Math.min(18, sudutX - dy * 0.4));
            vY = dx * 0.2;
        });
        var lepas = function () {
            if (!diseret) return;
            diseret = false;
            scene.classList.remove('drag');
            // klik singkat pada kardus = membal
            if (jarak < 5 && kubusAwal) {
                var k = kubusAwal;
                k.classList.remove('pop');
                void k.offsetWidth;
                k.classList.add('pop');
                setTimeout(function () { k.classList.remove('pop'); }, 700);
                vY = 6;
            }
        };
        scene.addEventListener('pointerup', lepas);
        scene.addEventListener('pointercancel', lepas);
    }
})();