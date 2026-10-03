# BlPay — Maliyyə İdarəetmə Sistemi

Fərdi gəlir, xərc və hədəfləri idarə etmək üçün PHP/MySQL əsaslı, təhlükəsiz və istifadəsi asan veb tətbiq. Bootstrap 5 ilə responsive (mobil uyğun) interfeys və PWA dəstəyi ilə telefondan əlavə ekran kimi quraşdırmaq mümkündür.

## Xüsusiyyətlər

### İstifadəçi sistemi
- İki addımlı qeydiyyat: hesab yaratma + gəlir/xərc kateqoriyalarının seçimi (xərc kateqoriyaları üçün aylıq limit təyini)
- Login (e-poçt) və şifrə ilə giriş, "Məni yadda saxla" funksiyası
- IP üzrə giriş cəhdlərinin məhdudlaşdırılması (brute-force qorunması)
- Rol əsaslı giriş: `admin` və `user`

### Gəlir və xərc idarəetməsi
- Gəlirlər və xərclərin əlavə edilməsi, redaktə edilməsi, silinməsi
- Kateqoriyalar üzrə bölgü (istifadəçiyə fərdi kateqoriyalar + standart kateqoriyalar)
- Hesab növü: Nagd, Kredit kartı, Debet kartı
- Qeydlər və tarix izləməsi
- Xərc kateqoriyaları üçün aylıq limit nəzarəti

### Maliyyə hədəfləri
- Hədəf yaratma (məbləğ, başlanğıc/hədəf tarixi, prioritet: yüksək/orta/aşağı)
- Hədəfə artırmə/azaltma əməliyyatları, avtomatik gedişat hesabatı
- Status izləmə: aktiv, tamamlanıb, ləğv edilib

### Hesabat və analitika
- Ümumi balans, aylıq gəlir/xərc xülasələri
- Aylıq dinamika və təxmin edilən qalıq (proqnoz)
- Hesab növü və kateqoriya üzrə bölgü
- Təhlükəsizlik jurnalı (audit log)

### Admin paneli (`/admin/`)
- Sistem statistiki: istifadəçilər, əməliyyatlar, kateqoriyalar
- İstifadəçi idarəetməsi: status dəyişmə (aktiv/deaktiv), admin hüququ vermə, istifadəçi silmə

## Texnologiya yığını

| Komponent | Texnologiya |
|-----------|-------------|
| Backend | PHP 7.4+ (PDO) |
| Verilənlər bazası | MySQL 5.7+ / MariaDB |
| Frontend | Bootstrap 5, Vanilla JS |
| Arxitektura | Monolitik, MVC-yə bənzər (config / includes / səhifələr) |

## Tələblər

- PHP 7.4 və ya yuxarı (`pdo_mysql` ekstensiyası ilə)
- MySQL 5.7 / MariaDB 10.3 və ya yuxarı
- Apache, Nginx və ya PHP built-in server (XAMPP/WAMP/LAMP dəstəklənir)

## Quraşdırma

1. Repo-nu klonlayın və veb server qovluğuna yerləşdirin:
   ```bash
   git clone https://github.com/bliveks/blpay.git
   ```
2. `config/` qovluğunun PHP tərəfindən yazıla bilən olduğundan əmin olun.
3. Saytı brauzerdə açıb quraşdırıcıya daxil olun: `http://sayt-adresi/install/`
4. Formanı doldurun:
   - **Verilənlər bazası** — host, port, istifadəçi, şifrə, baza adı (baza mövcud deyilsə avtomatik yaradılır)
   - **Admin hesabı** — ad, soyad, e-poçt (login) və şifrə (ən azı 8 simvol)
5. "Quraşdırmanı icra et" düyməsi ilə bitirin. Quraşdırıcı:
   - Cədvəlləri `install/install.sql` əsasında yaradır
   - Admin hesabını `password_hash()` ilə yaradır və standart kateqoriyaları ona kopyalayır
   - `config/db.php` konfiqurasiyasını avtomatik yazır
   - `install/lock` faylı yaradır (təkrar icranın qarşısını alır)
6. `login.php` səhifəsindən yaratdığınız admin hesabı ilə daxil olun.

> **Təhlükəsizlik qeydi:** Quraşdırmadan sonra `install/` qovluğunu silin və ya yalnız admin tərəfindən əlçatan edin.

## Qovluq strukturu

```
blpay/
├── admin/                # Admin paneli
│   ├── index.php         # Sistem statistikasi
│   ├── users.php         # İstifadəçi idarəetməsi
│   ├── status_change.php # İstifadəçi statusunun dəyişdirilməsi
│   ├── css/ js/ includes/
├── config/
│   └── db.php            # Verilənlər bazası konfiqurasiyası (quraşdırıcı yazır)
├── includes/
│   ├── functions.php     # Köməkçi funksiyalar (seans, CSRF, audit, validasiya)
│   └── nav.php           # Naviqasiya menyusu
├── install/              # Quraşdırıcı (quraşdırmadan sonra silin)
│   ├── index.php
│   └── install.sql       # Boş verilənlər bazası strukturu
├── css/ js/ icons/       # Aktivlər və PWA ikonları
├── index.php             # Ana səhifə (dashboard)
├── login.php             # Giriş
├── qeydiyyat.php         # İki addımlı qeydiyyat
├── gelirler.php          # Gəlirlər
├── xercler.php           # Xərclər
├── kateqoriyalar.php     # Kateqoriya idarəetməsi
├── hedefler.php          # Maliyyə hədəfləri
├── hesabat.php           # Hesabatlar və analitika
├── ayarlar.php           # Hesab parametrləri
├── cixis.php             # Çıxış
└── manifest.json         # PWA manifesti
```

## Verilənlər bazası

| Cədvəl | Təsvir |
|--------|--------|
| `istifadeciler` | İstifadəçilər (ad, e-poçt, hash-lanmış şifrə, rol, status, aylıq cəmlər) |
| `kateqoriyalar` | Gəlir/xərc kateqoriyaları (fərdi və standart, limit məbləği) |
| `gelirler` | Gəlir qeydləri |
| `xercler` | Xərc qeydləri |
| `hedefler` | Maliyyə hədəfləri |
| `hedef_emeliyyatlar` | Hədəf üzrə artırmə/azaltma əməliyyatları |
| `giris_cehdleri` | IP üzrə giriş cəhdləri |
| `remember_tokens` | "Məni yadda saxla" tokenləri |
| `audit_log` | Sistem aktivlik jurnalı |

Bütün cədvəllər utf8mb4, InnoDB və xarici açar (FK) məhdudiyyətləri ilə yaradılır; istifadəçi silindikdə aid qeydlər avtomatik təmizlənir.

## Təhlükəsizlik

- Şifrələr `password_hash()` (bcrypt) ilə saxlanılır
- Bütün sorğular PDO prepared statements → SQL injection qorunması
- CSRF token validasiyası (bütün formalar)
- XSS əleyhinə input sanitation və output escaping
- 30 dəqiqəlik seans taym-autu
- IP üzrə giriş cəhd məhdudiyyəti
- Bütün kritik əməliyyatlar `audit_log`-da qeydə alınır

## İstifadə axını

1. `/install/` — sistem quraşdırılır, admin hesabı yaradılır
2. `login.php` — giriş
3. Gəlirlər / Xərclər — əməliyyatların qeydiyyatı
4. Kateqoriyalar — fərdi kateqoriyalar və limitlər
5. Hədəflər — birikmə hədəflərinin izlənməsi
6. Hesabat — aylıq xülasələr və dinamika
7. `/admin/` — istifadəçilərin idarə edilməsi (yalnız admin)

## Lisenziya

MIT
